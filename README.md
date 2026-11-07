# ZJMF-furryface

基于阿里云市场API的魔方财务人脸实名认证插件，支持活体检测与身份证人像比对。

## 安装

把插件放到：

```
public/plugins/certification/furryface/
```

后台插件管理启用，并填写两个AppCode。

## 配置

| 配置项 | 说明 |
| --- | --- |
| 活体检测AppCode | 阿里云市场活体检测授权码 |
| 人像比对AppCode | 阿里云市场人像比对授权码 |
| 活体检测页面标题 | 采集页标题 |
| 活体检测样式 | 样式1或样式2 |
| 活体检测动作 | LookLeft、LookRight、OpenMouth、BlinkEye、ShakeHead、NodHead |
| 动作互斥检测 | 动作不匹配时停止采集 |
| 防摄像头劫持 | 开启闪光检测 |
| 保存用户人脸图片 | 是否保存到本地 |
| 金额/免费次数 | 认证收费与免费次数 |

## 流程

用户扫码进入H5活体检测，完成后回调插件；插件取回人脸图片，再调用人像比对接口核验姓名与身份证号。

## 接口

| 接口 | 说明 |
| --- | --- |
| `POST /api/liveness/h5/token` | 获取活体检测地址 |
| `POST /api/liveness/h5/result` | 查询活体检测结果 |
| `POST /api-mall/api/face_id_card_yi_suo/check` | 身份证人像比对 |

## 说明

API按调用次数计费，费用由阿里云市场服务商收取。插件不存储身份证号与姓名，开启本地保存时除外。
