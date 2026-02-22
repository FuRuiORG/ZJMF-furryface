<?php
namespace certification\furryface\logic;

class Furryface
{
    /**
     * 本地校验身份证号码是否有效
     * @param string $idcard 身份证号码
     * @return array ['valid' => bool, 'msg' => string, 'info' => array]
     */
    public function validateIdCard($idcard)
    {
        $result = ['valid' => false, 'msg' => '', 'info' => []];
        
        if (empty($idcard)) {
            $result['msg'] = '身份证号码不能为空';
            return $result;
        }
        
        if (strlen($idcard) != 15 && strlen($idcard) != 18) {
            $result['msg'] = '身份证号码长度不正确';
            return $result;
        }
        
        if (strlen($idcard) == 15) {
            $idcard = $this->convert15to18($idcard);
        }
        
        if (!$this->check18IdCard($idcard)) {
            $result['msg'] = '身份证号码校验失败';
            return $result;
        }
        
        $result['valid'] = true;
        $result['msg'] = '身份证号码有效';
        $result['info'] = [
            'birthday' => substr($idcard, 6, 8),
            'sex' => (substr($idcard, 16, 1) % 2 == 1) ? '男' : '女',
            'province' => substr($idcard, 0, 2)
        ];
        
        return $result;
    }
    
    /**
     * 15位身份证转18位
     * @param string $idcard 15位身份证号
     * @return string 18位身份证号
     */
    private function convert15to18($idcard)
    {
        if (strlen($idcard) != 15) {
            return $idcard;
        }
        
        $idcard = substr($idcard, 0, 6) . '19' . substr($idcard, 6);
        $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $code = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        
        $total = 0;
        for ($i = 0; $i < 17; $i++) {
            $total += substr($idcard, $i, 1) * $weight[$i];
        }
        
        return $idcard . $code[$total % 11];
    }
    
    /**
     * 校验18位身份证号码
     * @param string $idcard 18位身份证号
     * @return bool
     */
    private function check18IdCard($idcard)
    {
        if (strlen($idcard) != 18) {
            return false;
        }
        
        $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $code = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        
        $total = 0;
        for ($i = 0; $i < 17; $i++) {
            $total += substr($idcard, $i, 1) * $weight[$i];
        }
        
        $checkCode = $code[$total % 11];
        $lastCode = strtoupper(substr($idcard, 17, 1));
        
        return $checkCode === $lastCode;
    }
    
    /**
     * 获取活体检测H5页面Token
     * @param string $appcode AppCode
     * @param array $params 参数数组
     * @param string $host API地址
     * @return array
     */
    public function getLiveToken($appcode, $params, $host)
    {
        if (empty($appcode)) {
            return [
                'code' => 400,
                'msg' => '活体检测AppCode未配置，请在后台设置'
            ];
        }
        
        $path = "/api/liveness/h5/token";
        $method = "POST";
        $headers = [];
        array_push($headers, "Authorization:APPCODE " . $appcode);
        array_push($headers, "Content-Type:application/x-www-form-urlencoded; charset=UTF-8");
        
        $bodys = http_build_query($params);
        $url = $host . $path;
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            return [
                'code' => 500,
                'msg' => '网络请求失败: ' . $curlError
            ];
        }
        
        if ($httpCode == 403) {
            return [
                'code' => 403,
                'msg' => 'AppCode无效或已过期，请检查配置'
            ];
        }
        
        if ($httpCode != 200) {
            return [
                'code' => $httpCode,
                'msg' => 'API请求失败，HTTP状态码: ' . $httpCode
            ];
        }
        
        $data = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'code' => 500,
                'msg' => 'API返回数据解析失败'
            ];
        }
        
        if (isset($data['code']) && $data['code'] != 200) {
            return [
                'code' => $data['code'],
                'msg' => $data['msg'] ?? 'API返回错误'
            ];
        }
        
        return $data;
    }
    
    /**
     * 获取活体检测结果
     * @param string $appcode AppCode
     * @param string $orderNo 订单号
     * @param string $host API地址
     * @return array
     */
    public function getLiveResult($appcode, $orderNo, $host)
    {
        if (empty($appcode)) {
            return [
                'code' => 400,
                'msg' => '活体检测AppCode未配置'
            ];
        }
        
        $path = "/api/liveness/h5/result";
        $method = "POST";
        $headers = [];
        array_push($headers, "Authorization:APPCODE " . $appcode);
        array_push($headers, "Content-Type:application/x-www-form-urlencoded; charset=UTF-8");
        
        $bodys = "orderNo=" . $orderNo;
        $url = $host . $path;
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result, true);
    }
    
    /**
     * 人像比对-身份证人脸比对
     * @param string $appcode AppCode
     * @param string $name 姓名
     * @param string $idcard 身份证号
     * @param string $image base64图片
     * @param string $host API地址
     * @return array
     */
    public function faceCompare($appcode, $name, $idcard, $image, $host)
    {
        if (empty($appcode)) {
            return [
                'code' => 400,
                'msg' => '人像比对AppCode未配置，请在后台设置'
            ];
        }
        
        $path = "/api-mall/api/face_id_card_yi_suo/check";
        $method = "POST";
        $headers = [];
        array_push($headers, "Authorization:APPCODE " . $appcode);
        array_push($headers, "Content-Type:application/x-www-form-urlencoded; charset=UTF-8");
        
        $params = [
            'idcard' => $idcard,
            'name' => $name,
            'image' => $image
        ];
        $bodys = http_build_query($params);
        $url = $host . $path;
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            return [
                'code' => 500,
                'msg' => '网络请求失败: ' . $curlError
            ];
        }
        
        if ($httpCode == 403) {
            return [
                'code' => 403,
                'msg' => '人像比对AppCode无效或已过期'
            ];
        }
        
        $data = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'code' => 500,
                'msg' => 'API返回数据解析失败'
            ];
        }
        
        return $data;
    }
    
    /**
     * 通过URL获取图片并转为base64
     * @param string $url 图片URL
     * @return string|false
     */
    public function imageUrlToBase64($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($curl);
        curl_close($curl);
        
        if ($result) {
            return base64_encode($result);
        }
        return false;
    }
    
    /**
     * 保存图片到本地并返回base64
     * @param string $imageUrl 图片URL
     * @param string $idcard 身份证号（用于文件名）
     * @return array ['success' => bool, 'base64' => string, 'path' => string]
     */
    public function saveImageToLocal($imageUrl, $idcard)
    {
        $result = ['success' => false, 'base64' => '', 'path' => ''];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $imageUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $imageData = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode != 200 || !$imageData) {
            return $result;
        }
        
        $randomDir = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $baseDir = dirname(__DIR__, 3) . '/public/plugins/certification/furryface/faceresult/' . $randomDir;
        
        if (!is_dir($baseDir)) {
            if (!mkdir($baseDir, 0755, true)) {
                return $result;
            }
        }
        
        $fileName = $idcard . '_' . date('YmdHis') . '.jpg';
        $filePath = $baseDir . '/' . $fileName;
        
        if (file_put_contents($filePath, $imageData)) {
            $result['success'] = true;
            $result['base64'] = base64_encode($imageData);
            $result['path'] = $filePath;
        }
        
        return $result;
    }
    
    /**
     * 生成二维码
     * 使用Google Chart API生成二维码
     * @param string $data 二维码内容
     * @param int $size 尺寸
     * @return string 二维码图片URL
     */
    public function generateQRCode($data, $size = 200)
    {
        return 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&chl=' . urlencode($data) . '&choe=UTF-8';
    }
}
?>
