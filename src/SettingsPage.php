<?php

namespace Wenprise\LicenseManager;

/**
 * 许可证管理类
 */
class SettingsPage
{
	/**
	 * 许可证管理器实例
	 *
	 * @var Manager
	 */
	private $license_manager;

	/**
	 * 插件名称
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * 插件名称
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * 插件版本
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * 许可证密钥选项名
	 *
	 * @var string
	 */
	private $license_key_option;

	/**
	 * 许可证状态选项名
	 *
	 * @var string
	 */
	private $license_status_option;

	/**
	 * 设置页面 slug
	 *
	 * @var string
	 */
	private $settings_page_slug;

	/**
	 * 设置页面标题
	 *
	 * @var string
	 */
	private $settings_page_title;

	/**
	 * 菜单标题
	 *
	 * @var string
	 */
	private $menu_title;

	/**
	 * AJAX 动作前缀
	 *
	 * @var string
	 */
	private $ajax_prefix;

	/**
	 * 文本域
	 *
	 * @var string
	 */
	private $text_domain;

	/**
	 * 构造函数
	 *
	 * @param string $plugin_name    插件名称
	 * @param string $plugin_version 插件版本
	 * @param string $text_domain    文本域，可选，默认为插件名称的小写形式
	 */
	public function __construct($plugin_name, $plugin_version, $text_domain = null)
	{
		$this->plugin_name    = $plugin_name;
		$this->plugin_version = $plugin_version;

		// 生成插件的小写形式，用于选项名和页面 slug
		$this->plugin_slug       = $this->generate_slug($plugin_name);
		$this->text_domain = $text_domain ?? $this->plugin_slug;

		// 设置选项名
		$this->license_key_option    = $this->plugin_slug . '_license_key';
		$this->license_status_option = $this->plugin_slug . '_license_status';

		// 设置页面相关
		$this->settings_page_slug  = $this->plugin_slug . '_settings';
		$this->settings_page_title = $plugin_name . ' ' . __('设置', $this->text_domain);
		$this->menu_title          = $plugin_name;

		// AJAX 前缀
		$this->ajax_prefix = $this->plugin_slug;

		// 初始化许可证管理器
		$this->license_manager = new Manager(
			$this->plugin_name,
			$this->plugin_version,
			'https://srv.wpcio.com/wp-json/wplm/v1',
			$this->license_key_option,
			$this->license_status_option
		);

		// 添加设置页面
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);

		// 注册 AJAX 处理函数
		add_action('wp_ajax_' . $this->ajax_prefix . '_activate_license', [$this, 'ajax_activate_license']);
		add_action('wp_ajax_' . $this->ajax_prefix . '_deactivate_license', [$this, 'ajax_deactivate_license']);

		// 将许可状态传递给编辑器
		add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
	}

	/**
	 * 生成插件的小写形式作为 slug
	 *
	 * @param string $name 插件名称
	 *
	 * @return string 生成的 slug
	 */
	private function generate_slug($name)
	{
		// 转换为小写，替换空格为下划线，移除非字母数字下划线字符
		$slug = strtolower($name);
		$slug = str_replace(' ', '_', $slug);

		return preg_replace('/[^a-z0-9_]/', '', $slug);
	}

	/**
	 * 添加设置页面
	 */
	public function add_settings_page()
	{
		add_options_page(
			$this->settings_page_title,
			$this->menu_title,
			'manage_options',
			$this->settings_page_slug,
			[$this, 'render_settings_page']
		);
	}

	/**
	 * 注册设置
	 */
	public function register_settings()
	{
		// 注册许可证密钥设置
		register_setting(
			$this->settings_page_slug,
			$this->license_key_option,
			[
				'type'              => 'string',
				'sanitize_callback' => [$this, 'sanitize_license_key'],
				'default'           => '',
			]
		);

		// 添加设置字段
		add_settings_section(
			$this->ajax_prefix . '_license_section',
			__('许可证设置', $this->text_domain),
			[$this, 'render_license_section'],
			$this->settings_page_slug
		);

		// 添加许可证密钥字段
		add_settings_field(
			$this->license_key_option,
			__('许可证密钥', $this->text_domain),
			[$this, 'render_license_key_field'],
			$this->settings_page_slug,
			$this->ajax_prefix . '_license_section'
		);
	}

	/**
	 * 渲染许可证部分说明
	 */
	public function render_license_section()
	{
		echo '<p>' . sprintf(__('输入您的许可证密钥来解锁 %s 的高级功能。', $this->text_domain), $this->plugin_name) . '</p>';
	}

	/**
	 * 渲染许可证密钥字段
	 */
	public function render_license_key_field()
	{
		$license_key    = $this->license_manager->get_license_key();
		$license_status = $this->license_manager->get_license_status();

		echo '<input type="text" id="' . esc_attr($this->license_key_option) . '" name="' . esc_attr($this->license_key_option) . '" value="' . esc_attr($license_key) . '" class="regular-text" />';

		// 只显示停用按钮，保存设置时自动激活
		if ($license_status === 'active') {
			echo ' <input type="button" class="button-secondary" id="' . esc_attr($this->ajax_prefix) . '_deactivate_license" value="' . __('停用许可证', $this->text_domain) . '" />';
			echo ' <span class="description" style="color:green;">' . __('已激活', $this->text_domain) . '</span>';
		} else {
			echo ' <span class="description">' . __('未激活 - 保存设置将自动激活许可证', $this->text_domain) . '</span>';
		}

		// 添加 nonce 字段
		wp_nonce_field($this->ajax_prefix . '_license_nonce', $this->ajax_prefix . '_license_nonce');

		// 添加 AJAX 处理脚本
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// 停用许可证
				$('#<?php echo esc_js($this->ajax_prefix); ?>_deactivate_license').on('click', function() {
					$.ajax({
						url     : ajaxurl,
						type    : 'POST',
						dataType: 'json',
						data    : {
							action: '<?php echo esc_js($this->ajax_prefix); ?>_deactivate_license',
							nonce : $('#<?php echo esc_js($this->ajax_prefix); ?>_license_nonce').val(),
						},
						success : function(response) {
							if (response.success) {
								alert(response.data.message)
								location.reload()
							} else {
								alert(response.data.message)
							}
						},
						error   : function() {
							alert('<?php echo esc_js(__('停用失败', $this->text_domain)); ?>')
						},
					})
				})
			})
		</script>
		<?php
	}

	/**
	 * 渲染设置页面
	 */
	public function render_settings_page()
	{
		if ( ! current_user_can('manage_options')) {
			return;
		}

		// 检查是否有自动激活结果需要显示
		$activation_result = get_transient($this->ajax_prefix . '_license_activation_result');

		if ($activation_result) {
			// 删除 transient，避免多次显示
			delete_transient($this->ajax_prefix . '_license_activation_result');

			// 显示通知
			$notice_class = $activation_result[ 'success' ] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . $notice_class . ' is-dismissible"><p>' . esc_html($activation_result[ 'message' ]) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields($this->settings_page_slug);
				do_settings_sections($this->settings_page_slug);

				// 添加许可证状态隐藏字段，防止保存设置时状态被重置
				$license_status = $this->license_manager->get_license_status();
				echo '<input type="hidden" name="' . esc_attr($this->license_status_option) . '" value="' . esc_attr($license_status) . '" />';

				submit_button(__('保存设置', $this->text_domain));
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX 激活许可证
	 */
	public function ajax_activate_license()
	{
		// 检查 nonce
		if ( ! isset($_POST[ 'nonce' ]) || ! wp_verify_nonce($_POST[ 'nonce' ], $this->ajax_prefix . '_license_nonce')) {
			wp_send_json_error(['message' => __('安全检查失败', $this->text_domain)]);
		}

		// 检查当前用户权限
		if ( ! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('您没有执行此操作的必要权限', $this->text_domain)]);
		}

		// 获取许可证密钥
		$license_key = isset($_POST[ 'license_key' ]) ? sanitize_text_field($_POST[ 'license_key' ]) : '';

		if (empty($license_key)) {
			wp_send_json_error(['message' => __('请输入许可证密钥', $this->text_domain)]);
		}

		// 使用许可证管理器激活许可证
		$result = $this->license_manager->activate_license($license_key);

		if ($result[ 'success' ]) {
			wp_send_json_success(['message' => $result[ 'message' ] ?? __('许可证激活成功！', $this->text_domain)]);
		} else {
			wp_send_json_error(['message' => $result[ 'error' ] ?? __('许可证激活失败', $this->text_domain)]);
		}
	}

	/**
	 * 许可证密钥的 sanitize 回调函数
	 * 检测许可证密钥是否变化，如果变化则自动运行激活逻辑
	 *
	 * @param string $license_key 许可证密钥
	 *
	 * @return string 处理后的许可证密钥
	 */
	public function sanitize_license_key($license_key)
	{
		// 清理输入
		$license_key = sanitize_text_field($license_key);

		// 获取当前保存的许可证密钥
		$current_license_key = get_option($this->license_key_option);

		// 如果许可证密钥发生变化且不为空，或者许可证未激活，则尝试激活
		// 使用 transient 防止循环调用
		$license_status = get_option($this->license_status_option, 'inactive');

		$should_activate = ( ! empty($license_key) &&
							 (($license_key !== $current_license_key) || $license_status !== 'active') &&
							 ! get_transient($this->plugin_slug . '_activating'));

		if ($should_activate) {
			// 设置一个防止循环的标志
			set_transient($this->plugin_slug . '_activating', true, 30);

			try {
				// 使用许可证管理器激活许可证
				$result = $this->license_manager->activate_license($license_key);

				// 记录激活结果到 transient，以便在设置页面显示通知
				if ($result[ 'success' ]) {
					set_transient($this->plugin_slug . '_activation_result', [
						'success' => true,
						'message' => $result[ 'message' ] ?? __('新许可证密钥已自动激活成功！', $this->text_domain),
					], 60);
				} else {
					set_transient($this->plugin_slug . '_activation_result', [
						'success' => false,
						'message' => $result[ 'error' ] ?? __('新许可证密钥自动激活失败', $this->text_domain),
					], 60);

					// 激活失败时设置状态为未激活
					update_option($this->license_status_option, 'inactive');
				}
			} finally {
				// 无论成功失败，都清除标志
				delete_transient($this->plugin_slug . '_activating');
			}
		}

		return $license_key;
	}

	/**
	 * AJAX 停用许可证
	 */
	public function ajax_deactivate_license()
	{
		// 检查 nonce
		if ( ! isset($_POST[ 'nonce' ]) || ! wp_verify_nonce($_POST[ 'nonce' ], $this->ajax_prefix . '_license_nonce')) {
			wp_send_json_error(['message' => __('安全检查失败', $this->text_domain)]);
		}

		// 检查当前用户权限
		if ( ! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('您没有执行此操作的必要权限', $this->text_domain)]);
		}

		// 使用许可证管理器停用许可证
		$result = $this->license_manager->deactivate_license();

		if ($result[ 'success' ]) {
			wp_send_json_success(['message' => $result[ 'message' ] ?? __('许可证停用成功！', $this->text_domain)]);
		} else {
			// 即使API调用失败，也更新本地状态为未激活
			update_option($this->license_status_option, 'inactive');
			wp_send_json_success(['message' => __('许可证已在本地停用（API调用失败）', $this->text_domain)]);
		}
	}

	/**
	 * 为编辑器加载许可证状态
	 */
	public function enqueue_editor_assets()
	{
		wp_localize_script(
			$this->license_status_option . '-editor-script',
			'wenpriseHotspotLicense',
			[
				'status'   => $this->license_manager->get_license_status(),
				'isActive' => $this->license_manager->is_license_active(),
			]
		);
	}

	/**
	 * 获取许可证状态
	 *
	 * @return bool 是否激活
	 */
	public function is_license_active()
	{
		return $this->license_manager->is_license_active();
	}
}
