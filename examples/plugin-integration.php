<?php
/**
 * 示例：如何在 WordPress 插件中集成 Wenprise License Manager
 */

// 确保直接访问时退出
if (!defined('ABSPATH')) {
    exit;
}

// 引入 Composer 自动加载器（假设您已通过 Composer 安装了此库）
// require_once __DIR__ . '/vendor/autoload.php';

use Wenprise\LicenseManager\LicenseManager;
use Wenprise\LicenseManager\Admin\LicenseSettingsPage;

/**
 * 示例插件类
 */
class Example_Plugin {
    /**
     * 插件版本
     */
    private $version = '1.0.0';
    
    /**
     * 许可证管理器实例
     */
    private $license_manager;
    
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        // 初始化许可证管理器
        $this->init_license_manager();
        
        // 添加许可证设置页面
        add_action('admin_menu', [$this, 'add_license_settings_page']);
        
        // 添加功能限制检查
        add_action('init', [$this, 'check_license_for_features']);
        
        // 定期检查许可证状态（每天一次）
        add_action('wp_scheduled_events', [$this, 'schedule_license_check']);
    }
    
    /**
     * 初始化许可证管理器
     */
    private function init_license_manager() {
        $this->license_manager = new LicenseManager(
            '示例插件',                // 插件名称
            $this->version,           // 插件版本
            'https://srv.wpcio.com/wp-json/wplm/v1', // API URL
            'example_plugin_license_key',    // 许可证密钥选项名
            'example_plugin_license_status', // 许可证状态选项名
            123                       // 产品 ID（可选）
        );
    }
    
    /**
     * 添加许可证设置页面
     */
    public function add_license_settings_page() {
        // 创建许可证设置页面实例
        $settings_page = new LicenseSettingsPage(
            $this->license_manager,   // 许可证管理器实例
            '示例插件许可证',         // 页面标题
            '许可证',                 // 菜单标题
            'example-plugin-license', // 菜单 slug
            'options-general.php'     // 父菜单（这里是设置菜单）
        );
    }
    
    /**
     * 检查许可证状态以启用/禁用功能
     */
    public function check_license_for_features() {
        // 检查许可证是否激活
        if (!$this->license_manager->is_license_active()) {
            // 许可证未激活，禁用高级功能
            add_filter('example_plugin_enable_premium_features', '__return_false');
            
            // 可选：添加管理通知
            add_action('admin_notices', [$this, 'show_license_notice']);
        } else {
            // 许可证已激活，启用高级功能
            add_filter('example_plugin_enable_premium_features', '__return_true');
        }
    }
    
    /**
     * 显示许可证通知
     */
    public function show_license_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings_url = admin_url('options-general.php?page=example-plugin-license');
        
        ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('示例插件：您的许可证未激活，高级功能已被禁用。', 'example-plugin'); ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php _e('激活许可证', 'example-plugin'); ?></a>
            </p>
        </div>
        <?php
    }
    
    /**
     * 安排许可证状态检查
     */
    public function schedule_license_check() {
        if (!wp_next_scheduled('example_plugin_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'example_plugin_daily_license_check');
        }
        
        add_action('example_plugin_daily_license_check', [$this, 'check_remote_license_status']);
    }
    
    /**
     * 检查远程许可证状态
     */
    public function check_remote_license_status() {
        // 仅当许可证密钥存在时才检查
        if ($this->license_manager->get_license_key()) {
            $this->license_manager->check_license_status();
        }
    }
    
    /**
     * 检查功能是否可用
     */
    public function is_feature_available($feature_name) {
        // 基本功能始终可用
        $basic_features = ['feature1', 'feature2'];
        if (in_array($feature_name, $basic_features)) {
            return true;
        }
        
        // 高级功能需要许可证
        return $this->license_manager->is_license_active();
    }
}

// 初始化插件
$example_plugin = Example_Plugin::get_instance();
