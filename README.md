<div align="center">

# 小小怪卡密验证系统

[![PHP Version](https://img.shields.io/badge/PHP-7.0+-blue.svg)](https://www.php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://www.mysql.com)
[![License](https://img.shields.io/github/license/xiaoxiaoguai-yyds/xxgkami)](https://github.com/xiaoxiaoguai-yyds/xxgkami/blob/main/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/xiaoxiaoguai-yyds/xxgkami)](https://github.com/xiaoxiaoguai-yyds/xxgkami/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/xiaoxiaoguai-yyds/xxgkami)](https://github.com/xiaoxiaoguai-yyds/xxgkami/issues)

一个功能强大、安全可靠的卡密验证系统，支持多种验证方式，提供完整的API接口。
适用于软件授权、会员验证等场景。


</div>

## ✨ 系统特点

### 🛡️ 安全可靠
- SHA1 加密存储卡密
- 设备绑定机制
  - [新] 管理员可后台解绑设备
  - [新] 解绑后允许新设备验证并绑定
- [新] 可配置是否允许同设备重复验证
- 防暴力破解
- 多重安全验证
- 数据加密存储

### 🔌 API支持
- RESTful API接口
- 多API密钥管理
- API调用统计
- 详细接口文档
- 支持POST/GET验证
- 设备ID绑定机制

### ⚡ 高效稳定
- 快速响应速度
- 稳定运行性能
- 性能优化设计
- 支持高并发访问

### 📊 数据统计
- 实时统计功能
- 详细数据分析
- 直观图表展示
- API调用统计
- 完整使用记录

## 🚀 快速开始

### 环境要求
```bash
PHP >= 7.0
MySQL >= 5.7
Apache/Nginx
```

### 安装步骤

1. 克隆项目
```bash
git clone https://github.com/xiaoxiaoguai-yyds/xxgkami.git
```

2. 上传到网站目录

3. 访问安装页面
```
http://your-domain/install/
```

4. 按照安装向导完成配置

## 📚 使用说明

### 管理员后台
1. 访问 `http://your-domain/admin.php`
2. 使用安装时设置的管理员账号登录
3. 进入管理面板

### API调用示例
```php
// POST请求示例
$url = 'http://your-domain/api/verify.php';
$data = [
    'card_key' => '您的卡密',
    'device_id' => '设备唯一标识'
];
$headers = ['X-API-KEY: 您的API密钥'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);
```

## 📋 功能列表

- [x] 卡密管理
  - [x] SHA1加密存储
  - [x] 批量生成卡密
  - [x] 自定义有效期
  - [x] 设备绑定
  - [x] [新] 设备解绑 (管理员操作)
  - [x] [新] 配置允许同设备重复验证
  - [x] [新] 支持时间卡和次数卡两种类型
  - [x] 停用/启用
  - [x] 导出Excel

- [x] 卡密验证中心
  - [x] [新] 无需设备ID直接验证卡密
  - [x] [新] 支持卡密查询功能
  - [x] [新] 弹窗显示卡密详细信息
  - [x] [新] 查看最近验证记录
  - [x] [新] 美观的响应式界面

- [x] API管理
  - [x] 多密钥支持
  - [x] 调用统计
  - [x] 状态管理
  - [x] 使用记录

- [x] 数据统计
  - [x] 使用趋势
  - [x] 实时统计
  - [x] 图表展示

## 🔄 系统升级

> **重要提示**：升级系统前请务必备份您的数据库，避免数据丢失。

### 数据库升级操作

如果您是从旧版本升级，需要执行以下数据库修改操作，以支持新功能：

1. **添加卡密类型支持**
```sql
ALTER TABLE `cards` 
ADD COLUMN `card_type` ENUM('time', 'count') DEFAULT 'time' COMMENT '卡密类型：time=时间卡,count=次数卡' AFTER `status`;
```

2. **添加卡密次数限制**
```sql
ALTER TABLE `cards` 
ADD COLUMN `total_count` INT DEFAULT 0 COMMENT '卡密总次数(次数卡使用)' AFTER `duration`,
ADD COLUMN `remaining_count` INT DEFAULT 0 COMMENT '剩余使用次数' AFTER `total_count`;
```

3. **添加验证方式字段**
```sql
ALTER TABLE `cards` 
ADD COLUMN `verify_method` VARCHAR(20) DEFAULT NULL COMMENT '验证方式:web=网页,post=API,get=API' AFTER `device_id`;
```

4. **更新已有卡密为时间卡**
```sql
UPDATE `cards` SET `card_type` = 'time' WHERE `card_type` IS NULL;
```

5. **将永久卡密的duration设为0**
```sql
UPDATE `cards` SET `duration` = 0 WHERE `duration` IS NULL OR `duration` <= 0;
```

执行这些SQL语句后，您的数据库将支持新版本的所有功能，同时保留原有数据。

### 文件升级

1. 备份您当前的`config.php`文件
2. 上传新版本的所有文件到您的网站目录
3. 恢复您的`config.php`文件
4. 访问网站，系统会自动完成其余配置



## 🤝 参与贡献

1. Fork 本仓库
2. 创建新的分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

## 📞 联系方式

- 作者：小小怪
- Email：xxgyyds@vip.qq.com
- GitHub：[@xiaoxiaoguai-yyds](https://github.com/xiaoxiaoguai-yyds)

## 📄 开源协议

本项目采用 MIT 协议开源，详见 [LICENSE](LICENSE) 文件。

## ⭐ Star 历史

[![Star History Chart](https://api.star-history.com/svg?repos=xiaoxiaoguai-yyds/xxgkami&type=Date)](https://star-history.com/#xiaoxiaoguai-yyds/xxgkami&Date)

## 🙏 鸣谢
感谢所有为这个项目做出贡献的开发者！

## 💝 友情赞助

如果这个项目对您有帮助，欢迎赞助支持我们的开发工作！

<div align="center">
    <table>
        <tr>
            <td align="center">
                <img src="https://www.xxg-yyds.com/img/wx.png" alt="微信赞助" width="300px">
                <br>
                <b>微信赞助</b>
            </td>
            <td align="center">
                <img src="https://www.xxg-yyds.com/img/zfb.jpg" alt="支付宝赞助" width="300px">
                <br>
                <b>支付宝赞助</b>
            </td>
        </tr>
    </table>
</div>

### 赞助说明

- 赞助金额不限，随心随意
- 赞助后可以在备注里留下您的称呼和留言
- 所有赞助都将用于：
  - 服务器维护费用
  - 功能开发和优化
  - 文档编写和维护
  - 社区建设

### 其他支持方式

- 点个 Star ⭐
- 推荐给身边的朋友
- 提交 Issue 或 PR
- 参与项目讨论 
