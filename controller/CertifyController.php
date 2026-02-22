<?php
namespace certification\furryface\controller;

use certification\furryface\logic\Furryface;

class CertifyController
{
    /**
     * 活体检测回调接口
     * 当用户完成活体检测后，阿里云会跳转到此地址
     */
    public function callback()
    {
        $orderNo = $_GET['orderNo'] ?? '';
        
        if (empty($orderNo)) {
            return $this->jsonResponse(400, '订单号不存在');
        }
        
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        if (!file_exists($sessionFile)) {
            return $this->jsonResponse(400, '认证会话不存在或已过期');
        }
        
        $sessionData = json_decode(file_get_contents($sessionFile), true);
        if (!$sessionData || (time() - $sessionData['time']) > 7200) {
            return $this->jsonResponse(400, '认证会话已过期');
        }
        
        $this->updateSessionStatus($orderNo, 'callback_received');
        
        return $this->jsonResponse(200, '回调成功', [
            'orderNo' => $orderNo,
            'status' => 'callback_received'
        ]);
    }
    
    /**
     * 轮询检查认证状态
     * 前端通过此接口轮询认证进度
     */
    public function check()
    {
        $orderNo = $_GET['orderNo'] ?? '';
        
        if (empty($orderNo)) {
            return $this->jsonResponse(400, '订单号不存在');
        }
        
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        if (!file_exists($sessionFile)) {
            return $this->jsonResponse(400, '认证会话不存在或已过期');
        }
        
        $sessionData = json_decode(file_get_contents($sessionFile), true);
        if (!$sessionData || (time() - $sessionData['time']) > 7200) {
            return $this->jsonResponse(400, '认证会话已过期');
        }
        
        $config = $this->getPluginConfig();
        $con = file_exists(dirname(__DIR__) . "/config/config.php") ? require dirname(__DIR__) . "/config/config.php" : [];
        $_config = array_merge($con, $config);
        
        $logic = new Furryface();
        $liveAppcode = $_config['live_app_code'];
        $faceAppcode = $_config['face_app_code'];
        $liveHost = $_config['live_url'];
        $faceHost = $_config['face_url'];
        
        $liveResult = $logic->getLiveResult($liveAppcode, $orderNo, $liveHost);
        
        if (!isset($liveResult['code']) || $liveResult['code'] != 200) {
            return $this->jsonResponse(200, '正在等待活体检测...', [
                'status' => 4,
                'orderNo' => $orderNo
            ]);
        }
        
        $result = $liveResult['data']['result'] ?? 2;
        if ($result == 2) {
            return $this->jsonResponse(200, '正在等待活体检测...', [
                'status' => 4,
                'orderNo' => $orderNo
            ]);
        }
        
        if ($result == 1) {
            $failMsg = $liveResult['data']['desc'] ?? '活体检测不通过';
            $this->clearSession($orderNo);
            $this->updateCertifiStatus($sessionData['type'], [
                'status' => 2,
                'auth_fail' => $failMsg,
                'certify_id' => $orderNo
            ]);
            return $this->jsonResponse(200, $failMsg, [
                'status' => 2,
                'orderNo' => $orderNo
            ]);
        }
        
        $faceImageUrl = $liveResult['data']['faceImageUrl'] ?? '';
        if (empty($faceImageUrl)) {
            $this->clearSession($orderNo);
            return $this->jsonResponse(400, '未获取到人脸照片');
        }
        
        $imageBase64 = $this->getFaceImageBase64($logic, $faceImageUrl, $sessionData['card'], $_config);
        if (!$imageBase64) {
            $this->clearSession($orderNo);
            return $this->jsonResponse(400, '人脸照片获取失败');
        }
        
        $name = $sessionData['name'];
        $card = $sessionData['card'];
        $type = $sessionData['type'];
        
        $faceResult = $logic->faceCompare($faceAppcode, $name, $card, $imageBase64, $faceHost);
        
        $this->clearSession($orderNo);
        
        if (!isset($faceResult['code']) || $faceResult['code'] != 200) {
            $this->updateCertifiStatus($type, [
                'status' => 2,
                'auth_fail' => $faceResult['msg'] ?? '人像比对失败',
                'certify_id' => $orderNo
            ]);
            return $this->jsonResponse(200, $faceResult['msg'] ?? '人像比对失败', [
                'status' => 2,
                'orderNo' => $orderNo
            ]);
        }
        
        $resultCode = $faceResult['data']['resultCode'] ?? 1004;
        $score = $faceResult['data']['score'] ?? 0;
        $msg = $faceResult['data']['msg'] ?? '';
        
        if ($resultCode == 1001) {
            $this->updateCertifiStatus($type, [
                'status' => 1,
                'auth_fail' => '',
                'certify_id' => $orderNo
            ]);
            return $this->jsonResponse(200, '认证通过，相似度：' . ($score * 100) . '%', [
                'status' => 1,
                'score' => $score,
                'orderNo' => $orderNo
            ]);
        } elseif ($resultCode == 1002) {
            $this->updateCertifiStatus($type, [
                'status' => 2,
                'auth_fail' => '人脸相似度较低，无法确认为同一人',
                'certify_id' => $orderNo
            ]);
            return $this->jsonResponse(200, '人脸相似度较低，无法确认为同一人', [
                'status' => 2,
                'orderNo' => $orderNo
            ]);
        } elseif ($resultCode == 1003) {
            $this->updateCertifiStatus($type, [
                'status' => 2,
                'auth_fail' => '人脸比对结果为不同人',
                'certify_id' => $orderNo
            ]);
            return $this->jsonResponse(200, '人脸比对结果为不同人', [
                'status' => 2,
                'orderNo' => $orderNo
            ]);
        } else {
            $this->updateCertifiStatus($type, [
                'status' => 2,
                'auth_fail' => $msg ?: '人像比对失败',
                'certify_id' => $orderNo
            ]);
            return $this->jsonResponse(200, $msg ?: '人像比对失败', [
                'status' => 2,
                'orderNo' => $orderNo
            ]);
        }
    }
    
    /**
     * 获取人脸图片Base64
     * @param Furryface $logic
     * @param string $imageUrl
     * @param string $card
     * @param array $config
     * @return string|false
     */
    private function getFaceImageBase64($logic, $imageUrl, $card, $config)
    {
        $imageMode = $config['image_mode'] ?? 'url';
        
        if ($imageMode === 'local') {
            $localPath = $this->saveImageLocal($imageUrl, $card);
            if ($localPath && file_exists($localPath)) {
                $imageData = file_get_contents($localPath);
                return base64_encode($imageData);
            }
        }
        
        return $logic->imageUrlToBase64($imageUrl);
    }
    
    /**
     * 保存图片到本地
     * @param string $imageUrl
     * @param string $card
     * @return string|false
     */
    private function saveImageLocal($imageUrl, $card)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $imageUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $imageData = curl_exec($curl);
        curl_close($curl);
        
        if (!$imageData) {
            return false;
        }
        
        $randomDir = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $baseDir = dirname(__DIR__, 4) . '/public/plugins/certification/furryface/faceresult/' . $randomDir;
        
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        
        $fileName = $card . '_' . date('YmdHis') . '.jpg';
        $filePath = $baseDir . '/' . $fileName;
        
        if (file_put_contents($filePath, $imageData)) {
            return $filePath;
        }
        
        return false;
    }
    
    /**
     * 获取插件配置
     * @return array
     */
    private function getPluginConfig()
    {
        return [];
    }
    
    /**
     * 更新会话状态
     * @param string $orderNo
     * @param string $status
     */
    private function updateSessionStatus($orderNo, $status)
    {
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        if (file_exists($sessionFile)) {
            $data = json_decode(file_get_contents($sessionFile), true);
            $data['callback_status'] = $status;
            $data['callback_time'] = time();
            file_put_contents($sessionFile, json_encode($data));
        }
    }
    
    /**
     * 清除会话
     * @param string $orderNo
     */
    private function clearSession($orderNo)
    {
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    }
    
    /**
     * 更新认证状态
     * @param string $type
     * @param array $data
     */
    private function updateCertifiStatus($type, $data)
    {
        if ($type === 'company') {
            if (function_exists('updateCompanyCertifiStatus')) {
                updateCompanyCertifiStatus($data);
            }
        } else {
            if (function_exists('updatePersonalCertifiStatus')) {
                updatePersonalCertifiStatus($data);
            }
        }
    }
    
    /**
     * JSON响应
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return string
     */
    private function jsonResponse($code, $msg, $data = [])
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
