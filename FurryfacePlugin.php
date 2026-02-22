<?php
namespace certification\furryface;

class FurryfacePlugin extends \app\admin\lib\Plugin
{
    public $info = [
        "name" => "Furryface",
        "title" => "人脸实名认证",
        "description" => "FurryFace人脸实名认证插件-支持活体检测与人像比对",
        "status" => 1,
        "author" => "FurryCloud",
        "version" => "1.0",
        "help_url" => "https://market.aliyun.com/detail/cmapi00071987"
    ];

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    /**
     * 个人认证流程
     * @param array $certifi 认证信息
     * @return string HTML代码
     */
    public function personal($certifi)
    {
        $config = $this->getConfig();
        $con = file_exists(__DIR__ . "/config/config.php") ? require __DIR__ . "/config/config.php" : [];
        $_config = array_merge($con, $config);

        $logic = new logic\Furryface();
        $name = $certifi['name'];
        $card = $certifi['card'];

        $validateResult = $logic->validateIdCard($card);
        if (!$validateResult['valid']) {
            $data = [
                'status' => 2,
                'auth_fail' => $validateResult['msg'],
                'certify_id' => ''
            ];
            updatePersonalCertifiStatus($data);
            return "<div class=\"text-center py-5\"><h3 class=\"text-danger\">身份证校验失败</h3><p class=\"text-muted\">" . htmlspecialchars($validateResult['msg']) . "</p></div>";
        }

        $callbackUrl = $this->getCallbackUrl();
        $liveParams = $this->buildLiveParams($_config, $callbackUrl);

        $liveAppcode = $_config['live_app_code'];
        $liveHost = $_config['live_url'];
        $tokenResult = $logic->getLiveToken($liveAppcode, $liveParams, $liveHost);

        if (!isset($tokenResult['code']) || $tokenResult['code'] != 200) {
            $data = [
                'status' => 2,
                'auth_fail' => $tokenResult['msg'] ?? '获取活体检测页面失败',
                'certify_id' => ''
            ];
            updatePersonalCertifiStatus($data);
            return "<div class=\"text-center py-5\"><h3 class=\"text-danger\">活体检测初始化失败</h3><p class=\"text-muted\">" . htmlspecialchars($tokenResult['msg'] ?? '请稍后重试') . "</p></div>";
        }

        $liveUrl = $tokenResult['data']['url'] ?? '';
        $orderNo = $tokenResult['data']['orderNo'] ?? '';

        $this->saveCertifySession($orderNo, $name, $card, 'personal');

        $data = [
            'status' => 4,
            'auth_fail' => '',
            'certify_id' => $orderNo
        ];
        updatePersonalCertifiStatus($data);

        return $this->renderQRCodePage($liveUrl, $orderNo, 'personal');
    }

    /**
     * 企业认证流程
     * @param array $certifi 认证信息
     * @return string HTML代码
     */
    public function company($certifi)
    {
        $config = $this->getConfig();
        $con = file_exists(__DIR__ . "/config/config.php") ? require __DIR__ . "/config/config.php" : [];
        $_config = array_merge($con, $config);

        $logic = new logic\Furryface();
        $name = $certifi['name'];
        $card = $certifi['card'];

        $validateResult = $logic->validateIdCard($card);
        if (!$validateResult['valid']) {
            $data = [
                'status' => 2,
                'auth_fail' => $validateResult['msg'],
                'certify_id' => ''
            ];
            updateCompanyCertifiStatus($data);
            return "<div class=\"text-center py-5\"><h3 class=\"text-danger\">身份证校验失败</h3><p class=\"text-muted\">" . htmlspecialchars($validateResult['msg']) . "</p></div>";
        }

        $callbackUrl = $this->getCallbackUrl();
        $liveParams = $this->buildLiveParams($_config, $callbackUrl);

        $liveAppcode = $_config['live_app_code'];
        $liveHost = $_config['live_url'];
        $tokenResult = $logic->getLiveToken($liveAppcode, $liveParams, $liveHost);

        if (!isset($tokenResult['code']) || $tokenResult['code'] != 200) {
            $data = [
                'status' => 2,
                'auth_fail' => $tokenResult['msg'] ?? '获取活体检测页面失败',
                'certify_id' => ''
            ];
            updateCompanyCertifiStatus($data);
            return "<div class=\"text-center py-5\"><h3 class=\"text-danger\">活体检测初始化失败</h3><p class=\"text-muted\">" . htmlspecialchars($tokenResult['msg'] ?? '请稍后重试') . "</p></div>";
        }

        $liveUrl = $tokenResult['data']['url'] ?? '';
        $orderNo = $tokenResult['data']['orderNo'] ?? '';

        $this->saveCertifySession($orderNo, $name, $card, 'company');

        $data = [
            'status' => 4,
            'auth_fail' => '',
            'certify_id' => $orderNo
        ];
        updateCompanyCertifiStatus($data);

        return $this->renderQRCodePage($liveUrl, $orderNo, 'company');
    }

    /**
     * 构建活体检测参数
     * @param array $config 配置
     * @param string $callbackUrl 回调地址
     * @return array
     */
    private function buildLiveParams($config, $callbackUrl)
    {
        $params = [
            'returnUrl' => $callbackUrl,
            'title' => $config['live_title'] ?? '实名认证',
        ];

        if (!empty($config['style'])) {
            $params['style'] = $config['style'];
        }

        if (!empty($config['action_live_list'])) {
            $params['actionLiveList'] = $config['action_live_list'];
        }

        $params['actionMutex'] = ($config['action_mutex'] ?? '') === 'true' ? 'true' : 'false';
        $params['antiCameraHack'] = ($config['anti_camera_hack'] ?? '') === 'true' ? 'true' : 'false';
        $params['showSuccess'] = ($config['show_success'] ?? '') === 'true' ? 'true' : 'false';
        $params['showFail'] = ($config['show_fail'] ?? '') === 'true' ? 'true' : 'false';
        $params['hideGuidePage'] = ($config['hide_guide_page'] ?? '') === 'true' ? 'true' : 'false';
        $params['foreLiveOn'] = ($config['fore_live_on'] ?? '') === 'true' ? 'true' : 'false';
        $params['backLiveOn'] = ($config['back_live_on'] ?? '') === 'true' ? 'true' : 'false';

        return $params;
    }

    /**
     * 前台自定义字段输出
     * @return array
     */
    public function collectionInfo()
    {
        return [];
    }

    /**
     * 轮询获取认证状态
     * @param array $certifi 认证信息
     * @return array|bool
     */
    public function getStatus($certifi)
    {
        $config = $this->getConfig();
        $con = file_exists(__DIR__ . "/config/config.php") ? require __DIR__ . "/config/config.php" : [];
        $_config = array_merge($con, $config);

        $orderNo = $certifi['certify_id'] ?? '';
        if (empty($orderNo)) {
            return [
                'status' => 2,
                'msg' => '认证订单号不存在'
            ];
        }

        $sessionData = $this->getCertifySession($orderNo);
        if (!$sessionData) {
            return [
                'status' => 2,
                'msg' => '认证会话已过期，请重新认证'
            ];
        }

        $logic = new logic\Furryface();
        $liveAppcode = $_config['live_app_code'];
        $faceAppcode = $_config['face_app_code'];
        $liveHost = $_config['live_url'];
        $faceHost = $_config['face_url'];

        $liveResult = $logic->getLiveResult($liveAppcode, $orderNo, $liveHost);

        if (!isset($liveResult['code']) || $liveResult['code'] != 200) {
            return [
                'status' => 4,
                'msg' => $liveResult['msg'] ?? '正在等待活体检测...'
            ];
        }

        $result = $liveResult['data']['result'] ?? 2;
        if ($result == 2) {
            return [
                'status' => 4,
                'msg' => '正在等待活体检测...'
            ];
        }

        if ($result == 1) {
            $failMsg = $liveResult['data']['desc'] ?? '活体检测不通过';
            $this->clearCertifySession($orderNo);
            $this->updateStatus($sessionData['type'], [
                'status' => 2,
                'auth_fail' => $failMsg,
                'certify_id' => $orderNo
            ]);
            return [
                'status' => 2,
                'msg' => $failMsg
            ];
        }

        $faceImageUrl = $liveResult['data']['faceImageUrl'] ?? '';
        if (empty($faceImageUrl)) {
            $this->clearCertifySession($orderNo);
            return [
                'status' => 2,
                'msg' => '未获取到人脸照片'
            ];
        }

        $imageBase64 = $logic->imageUrlToBase64($faceImageUrl);
        if (!$imageBase64) {
            $this->clearCertifySession($orderNo);
            return [
                'status' => 2,
                'msg' => '人脸照片获取失败'
            ];
        }

        if (($_config['save_face_image'] ?? '') === 'true') {
            $logic->saveImageToLocal($faceImageUrl, $sessionData['card']);
        }

        $name = $sessionData['name'];
        $card = $sessionData['card'];
        $type = $sessionData['type'];

        $faceResult = $logic->faceCompare($faceAppcode, $name, $card, $imageBase64, $faceHost);

        $this->clearCertifySession($orderNo);

        if (!isset($faceResult['code']) || $faceResult['code'] != 200) {
            $this->updateStatus($type, [
                'status' => 2,
                'auth_fail' => $faceResult['msg'] ?? '人像比对失败',
                'certify_id' => $orderNo
            ]);
            return [
                'status' => 2,
                'msg' => $faceResult['msg'] ?? '人像比对失败'
            ];
        }

        $resultCode = $faceResult['data']['resultCode'] ?? 1004;
        $score = $faceResult['data']['score'] ?? 0;
        $msg = $faceResult['data']['msg'] ?? '';

        if ($resultCode == 1001) {
            $this->updateStatus($type, [
                'status' => 1,
                'auth_fail' => '',
                'certify_id' => $orderNo
            ]);
            return [
                'status' => 1,
                'msg' => '认证通过，相似度：' . ($score * 100) . '%'
            ];
        } elseif ($resultCode == 1002) {
            $this->updateStatus($type, [
                'status' => 2,
                'auth_fail' => '人脸相似度较低，无法确认为同一人',
                'certify_id' => $orderNo
            ]);
            return [
                'status' => 2,
                'msg' => '人脸相似度较低，无法确认为同一人'
            ];
        } elseif ($resultCode == 1003) {
            $this->updateStatus($type, [
                'status' => 2,
                'auth_fail' => '人脸比对结果为不同人',
                'certify_id' => $orderNo
            ]);
            return [
                'status' => 2,
                'msg' => '人脸比对结果为不同人'
            ];
        } else {
            $this->updateStatus($type, [
                'status' => 2,
                'auth_fail' => $msg ?: '人像比对失败',
                'certify_id' => $orderNo
            ]);
            return [
                'status' => 2,
                'msg' => $msg ?: '人像比对失败'
            ];
        }
    }

    /**
     * 获取回调URL
     * @return string
     */
    private function getCallbackUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host . '/verified';
    }

    /**
     * 保存认证会话信息
     * @param string $orderNo 订单号
     * @param string $name 姓名
     * @param string $card 身份证号
     * @param string $type 类型 personal/company
     */
    private function saveCertifySession($orderNo, $name, $card, $type)
    {
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        $data = [
            'name' => $name,
            'card' => $card,
            'type' => $type,
            'time' => time()
        ];
        file_put_contents($sessionFile, json_encode($data));
    }

    /**
     * 获取认证会话信息
     * @param string $orderNo 订单号
     * @return array|false
     */
    private function getCertifySession($orderNo)
    {
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        if (file_exists($sessionFile)) {
            $content = file_get_contents($sessionFile);
            $data = json_decode($content, true);
            if ($data && (time() - $data['time']) < 7200) {
                return $data;
            }
        }
        return false;
    }

    /**
     * 清除认证会话
     * @param string $orderNo 订单号
     */
    private function clearCertifySession($orderNo)
    {
        $sessionFile = sys_get_temp_dir() . '/furryface_' . $orderNo . '.json';
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
    }

    /**
     * 更新认证状态
     * @param string $type 类型
     * @param array $data 数据
     */
    private function updateStatus($type, $data)
    {
        if ($type === 'company') {
            updateCompanyCertifiStatus($data);
        } else {
            updatePersonalCertifiStatus($data);
        }
    }

    /**
     * 返回活体检测URL，前端使用qrcode库生成二维码
     * @param string $liveUrl 活体检测URL
     * @param string $orderNo 订单号
     * @param string $type 认证类型
     * @return string HTML代码
     */
    private function renderQRCodePage($liveUrl, $orderNo, $type)
    {
        $pluginUrl = $this->getPluginUrl();
        $liveUrlJson = json_encode($liveUrl);
        
        return '<div style="text-align:center;padding:20px;">
            <p style="font-size:16px;color:#333;margin-bottom:15px;">请使用手机扫描二维码完成人脸认证</p>
            <div id="qrcode" style="display:inline-block;padding:15px;background:#fff;border:1px solid #eee;border-radius:8px;"></div>
            <p style="font-size:12px;color:#999;margin-top:15px;">认证订单：' . htmlspecialchars($orderNo) . '</p>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            (function() {
                var liveUrl = ' . $liveUrlJson . ';
                new QRCode(document.getElementById("qrcode"), {
                    text: liveUrl,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            })();
        </script>';
    }
    
    /**
     * 获取插件URL
     * @return string
     */
    private function getPluginUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host . '/plugins/certification/furryface';
    }
}
?>
