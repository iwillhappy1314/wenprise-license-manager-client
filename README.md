# Wenprise License Manager

WordPress 插件许可证管理库，提供简单易用的许可证验证、激活和管理功能。

## 功能特点

- 许可证密钥验证
- 许可证激活与停用
- 许可证状态检查
- 后台设置页面集成
- 灵活的配置选项
- 简单的 API 接口

## 安装

通过 Composer 安装:

```bash
composer require wenprise/license-manager
```

## 基本用法

### 初始化许可证管理器

```php
use Wenprise\LicenseManager\Manager;

// 创建许可证管理器实例
$license_manager = new Manager(
    '插件名称',                       // 插件名称
    '1.0.0',                         // 插件版本
    'https://srv.wpcio.com/wp-json/wplm/v1', // API URL
    'your_plugin_license_key',       // 许可证密钥选项名（可选）
    'your_plugin_license_status',    // 许可证状态选项名（可选）
    123                              // 产品 ID（可选）
);
```

### 验证许可证

```php
// 验证许可证
$result = $license_manager->validate_license('your-license-key');

if ($result['success']) {
    // 验证成功
    $license_data = $result['license'];
    // 处理许可证数据...
} else {
    // 验证失败
    $error_message = $result['error'];
    // 处理错误...
}
```

### 激活许可证

```php
// 激活许可证
$result = $license_manager->activate_license('your-license-key');

if ($result['success']) {
    // 激活成功
} else {
    // 激活失败
    $error_message = $result['error'];
}
```

### 停用许可证

```php
// 停用许可证
$result = $license_manager->deactivate_license();

if ($result['success']) {
    // 停用成功
} else {
    // 停用失败
    $error_message = $result['error'];
}
```

### 检查许可证状态

```php
// 检查许可证状态
$result = $license_manager->check_license_status();

if ($result['success']) {
    // 检查成功
    $license_status = $result['status']; // active 或 inactive
} else {
    // 检查失败
    $error_message = $result['error'];
}
```

### 检查许可证是否激活

```php
// 检查许可证是否激活
if ($license_manager->is_license_active()) {
    // 许可证已激活，启用高级功能
} else {
    // 许可证未激活，禁用高级功能
}
```

## 添加许可证设置页面

```php
use Wenprise\LicenseManager\SettingsPage;

$settings = new SettingsPage(
			'Wenprise Hotspot Block',
			'1.0',
			'wenprise-hotspot-block'
		);
```

## 完整插件集成示例

请参考 `examples/plugin-integration.php` 文件，了解如何在 WordPress 插件中完整集成许可证管理功能。

## API 服务器要求

许可证管理库默认与 Wenprise 许可证服务器 API 兼容，需要以下端点：

- `/validate` - 验证许可证
- `/activate` - 激活许可证
- `/deactivate` - 停用许可证
- `/status` - 检查许可证状态

如果您使用自定义许可证服务器，请确保实现这些端点并返回兼容的响应格式。

## 安全性考虑

- 建议对包含许可证验证逻辑的代码进行加密保护（如使用 ionCube 或 YAK Pro）
- 定期检查许可证状态，防止未授权使用
- 考虑添加额外的安全措施，如域名绑定、使用限制等

## 许可证

MIT

## 作者

Wenprise
