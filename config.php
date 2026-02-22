<?php
return [
    "amount" => [
        "title" => "金额",
        "type" => "text",
        "value" => 0,
        "tip" => "支付金额，大于0时需要支付"
    ],
    "free" => [
        "title" => "免费认证次数",
        "type" => "text",
        "value" => 0,
        "tip" => "免费认证次数"
    ],
    "live_app_code" => [
        "title" => "活体检测AppCode",
        "type" => "text",
        "value" => "",
        "tip" => "活体检测API的AppCode"
    ],
    "face_app_code" => [
        "title" => "人像比对AppCode",
        "type" => "text",
        "value" => "",
        "tip" => "人像比对API的AppCode"
    ],
    "tip" => [
        "title" => "获取人像比对AppCode",
        "type" => "radio",
        "options" => [
            "" => "人像比对AppCode开通",
            "true" => "https://market.aliyun.com/detail/cmapi00066582"
        ],
        "value" => "",
    ],
    "save_face_image" => [
        "title" => "保存用户人脸图片",
        "type" => "radio",
        "options" => [
            "" => "不保存",
            "true" => "保存到本地（插件路径的/faceresult目录下）"
        ],
        "value" => "",
        "tip" => "开启后会将用户人脸图片保存到服务器本地，路径：/public/plugins/certification/furryface/faceresult/"
    ],
    "live_title" => [
        "title" => "活体检测页面标题",
        "type" => "text",
        "value" => "FurryCloud人脸认证",
        "tip" => "采集页面的标题文本，最长32个字符（例如：FurryCloud人脸认证）"
    ],
    "style" => [
        "title" => "活体检测样式",
        "type" => "select",
        "options" => [
            "1" => "样式1：头部动作+局部动作随机组合",
            "2" => "样式2：仅局部动作（眨眼/张嘴）"
        ],
        "value" => "1",
        "tip" => "样式1：摇头/点头/左转/右转+眨眼/张嘴随机组合；样式2：仅眨眼/张嘴"
    ],
    "action_live_list" => [
        "title" => "活体检测动作",
        "type" => "text",
        "value" => "",
        "tip" => "多个动作逗号分隔：LookLeft(左转),LookRight(右转),OpenMouth(张嘴),BlinkEye(眨眼),ShakeHead(摇头),NodHead(点头)。留空使用默认"
    ],
    "action_mutex" => [
        "title" => "动作互斥检测",
        "type" => "radio",
        "options" => [
            "" => "关闭",
            "true" => "开启"
        ],
        "value" => "",
        "tip" => "开启后动作不匹配立即停止采集"
    ],
    "anti_camera_hack" => [
        "title" => "防摄像头劫持",
        "type" => "radio",
        "options" => [
            "" => "关闭",
            "true" => "开启"
        ],
        "value" => "",
        "tip" => "开启闪光防摄像头劫持检测"
    ],
    "show_success" => [
        "title" => "显示成功结果页",
        "type" => "radio",
        "options" => [
            "" => "不显示",
            "true" => "显示"
        ],
        "value" => "true",
        "tip" => "是否展示采集成功的结果页"
    ],
    "show_fail" => [
        "title" => "显示失败结果页",
        "type" => "radio",
        "options" => [
            "" => "不显示",
            "true" => "显示"
        ],
        "value" => "true",
        "tip" => "是否展示采集失败的结果页面"
    ],
    "hide_guide_page" => [
        "title" => "隐藏引导页",
        "type" => "radio",
        "options" => [
            "" => "显示引导页",
            "true" => "隐藏引导页"
        ],
        "value" => "",
        "tip" => "是否隐藏活体检测引导页"
    ],
    "fore_live_on" => [
        "title" => "前端活体算法",
        "type" => "radio",
        "options" => [
            "" => "关闭",
            "true" => "开启"
        ],
        "value" => "true",
        "tip" => "开启前端小模型活体算法"
    ],
    "back_live_on" => [
        "title" => "云端活体算法",
        "type" => "radio",
        "options" => [
            "" => "关闭",
            "true" => "开启"
        ],
        "value" => "",
        "tip" => "开启云端大模型活体算法"
    ]
];
?>
