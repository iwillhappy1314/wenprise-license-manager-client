<?php

namespace Wenprise\LicenseManager\Admin;

use Wenprise\LicenseManager\LicenseManager;

/**
 * 许可证设置页面类
 */
class LicenseSettingsPage
{
    /**
     * 许可证管理器实例
     *
     * @var LicenseManager
     */
    private $license_manager;

    /**
     * 菜单页面标题
     *
     * @var string
     */
    private $page_title;

    /**
     * 菜单标题
     *
     * @var string
     */
    private $menu_title;

    /**
     * 菜单页面 slug
     *
     * @var string
     */
    private $menu_slug;

    /**
     * 父菜单 slug
     *
     * @var string|null
     */
    private $parent_slug;

    /**
     * 构造函数
     *
     * @param LicenseManager $license_manager 许可证管理器实例
     * @param string $page_title 页面标题
     * @param string $menu_title 菜单标题
     * @param string $menu_slug 菜单 slug
     * @param string|null $parent_slug 父菜单 slug，如果为 null 则创建顶级菜单
     */
    public function __construct(
        LicenseManager $license_manager,
        string $page_title,
        string $menu_title,
        string $menu_slug,
        ?string $parent_slug = null
    ) {
        $this->license_manager = $license_manager;
        $this->page_title = $page_title;
        $this->menu_title = $menu_title;
        $this->menu_slug = $menu_slug;
        $this->parent_slug = $parent_slug;

        // 添加管理菜单
        add_action('admin_menu', [$this, 'add_license_menu']);

        // 注册设置
        add_action('admin_init', [$this, 'register_settings']);

        // 处理许可证激活/停用操作
        add_action('admin_init', [$this, 'handle_license_actions']);
    }

    /**
     * 添加许可证菜单
     */
    public function add_license_menu()
    {
        if ($this->parent_slug) {
            // 添加子菜单
            add_submenu_page(
                $this->parent_slug,
                $this->page_title,
                $this->menu_title,
                'manage_options',
                $this->menu_slug,
                [$this, 'render_settings_page']
            );
        } else {
            // 添加顶级菜单
            add_menu_page(
                $this->page_title,
                $this->menu_title,
                'manage_options',
                $this->menu_slug,
                [$this, 'render_settings_page'],
                'dashicons-lock'
            );
        }
    }

    /**
     * 注册设置
     */
    public function register_settings()
    {
        // 注册设置组
        add_settings_section(
            'wenprise_license_section',
            '许可证设置',
            [$this, 'render_section_info'],
            $this->menu_slug
        );

        // 注册许可证密钥字段
        add_settings_field(
            $this->license_manager->get_license_key_option_name(),
            '许可证密钥',
            [$this, 'render_license_key_field'],
            $this->menu_slug,
            'wenprise_license_section'
        );

        // 注册设置
        register_setting(
            $this->menu_slug,
            $this->license_manager->get_license_key_option_name(),
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );
    }

    /**
     * 渲染设置页面
     */
    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->page_title); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->menu_slug);
                do_settings_sections($this->menu_slug);
                submit_button('保存设置');
                ?>
            </form>
            
            <?php $this->render_license_actions(); ?>
        </div>
        <?php
    }

    /**
     * 渲染设置区域信息
     */
    public function render_section_info()
    {
        echo '<p>请输入您的许可证密钥以激活产品功能。</p>';
    }

    /**
     * 渲染许可证密钥字段
     */
    public function render_license_key_field()
    {
        $license_key = $this->license_manager->get_license_key();
        $license_status = $this->license_manager->get_license_status();
        $status_class = $license_status === 'active' ? 'license-active' : 'license-inactive';
        $status_text = $license_status === 'active' ? '已激活' : '未激活';
        
        ?>
        <input type="text" 
               id="<?php echo esc_attr($this->license_manager->get_license_key_option_name()); ?>" 
               name="<?php echo esc_attr($this->license_manager->get_license_key_option_name()); ?>" 
               value="<?php echo esc_attr($license_key); ?>" 
               class="regular-text"
               <?php echo $license_status === 'active' ? 'readonly' : ''; ?>
        />
        <span class="license-status <?php echo esc_attr($status_class); ?>">
            <?php echo esc_html($status_text); ?>
        </span>
        
        <style>
            .license-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                margin-left: 10px;
                font-weight: bold;
            }
            .license-active {
                background-color: #dff0d8;
                color: #3c763d;
            }
            .license-inactive {
                background-color: #f2dede;
                color: #a94442;
            }
        </style>
        <?php
    }

    /**
     * 渲染许可证操作按钮
     */
    public function render_license_actions()
    {
        $license_status = $this->license_manager->get_license_status();
        $nonce = wp_create_nonce('wenprise_license_action');
        
        ?>
        <div class="license-actions" style="margin-top: 20px;">
            <h3>许可证管理</h3>
            
            <?php if ($license_status === 'active') : ?>
                <form method="post" action="">
                    <input type="hidden" name="wenprise_license_action" value="deactivate" />
                    <input type="hidden" name="wenprise_license_nonce" value="<?php echo esc_attr($nonce); ?>" />
                    <?php submit_button('停用许可证', 'secondary', 'submit', false); ?>
                </form>
            <?php else : ?>
                <form method="post" action="">
                    <input type="hidden" name="wenprise_license_action" value="activate" />
                    <input type="hidden" name="wenprise_license_nonce" value="<?php echo esc_attr($nonce); ?>" />
                    <?php submit_button('激活许可证', 'primary', 'submit', false); ?>
                </form>
            <?php endif; ?>
            
            <form method="post" action="" style="margin-top: 10px;">
                <input type="hidden" name="wenprise_license_action" value="check_status" />
                <input type="hidden" name="wenprise_license_nonce" value="<?php echo esc_attr($nonce); ?>" />
                <?php submit_button('检查许可证状态', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    /**
     * 处理许可证操作
     */
    public function handle_license_actions()
    {
        if (!isset($_POST['wenprise_license_action']) || !isset($_POST['wenprise_license_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['wenprise_license_nonce'], 'wenprise_license_action')) {
            add_settings_error(
                'wenprise_license',
                'invalid_nonce',
                '安全验证失败，请重试。',
                'error'
            );
            return;
        }

        $action = sanitize_text_field($_POST['wenprise_license_action']);
        $result = [];

        switch ($action) {
            case 'activate':
                $result = $this->license_manager->activate_license();
                break;
                
            case 'deactivate':
                $result = $this->license_manager->deactivate_license();
                break;
                
            case 'check_status':
                $result = $this->license_manager->check_license_status();
                break;
        }

        if (isset($result['success']) && $result['success']) {
            add_settings_error(
                'wenprise_license',
                'license_updated',
                $result['message'] ?? '许可证操作成功。',
                'success'
            );
        } else {
            add_settings_error(
                'wenprise_license',
                'license_error',
                $result['error'] ?? '许可证操作失败。',
                'error'
            );
        }
    }
}
