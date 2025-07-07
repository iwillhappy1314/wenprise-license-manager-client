<?php

namespace Wenprise\LicenseManager;

/**
 * WordPress 插件许可证管理类
 */
class Manager
{
    /**
     * 插件版本
     *
     * @var string
     */
    private $version;

    /**
     * 插件名称
     *
     * @var string
     */
    private $plugin_name;

    /**
     * 许可证服务器 URL
     *
     * @var string
     */
    private $api_url;

    /**
     * 许可证密钥选项名称
     *
     * @var string
     */
    private $license_key_option;

    /**
     * 许可证状态选项名称
     *
     * @var string
     */
    private $license_status_option;

    /**
     * 产品 ID
     *
     * @var int|null
     */
    private $product_id;

    /**
     * 构造函数
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     * @param string $api_url 许可证服务器 URL
     * @param string $license_key_option 许可证密钥选项名称
     * @param string $license_status_option 许可证状态选项名称
     * @param int|null $product_id 产品 ID
     */
    public function __construct(
        string $plugin_name,
        string $version,
        string $api_url = 'https://srv.wpcio.com/wp-json/wplm/v1',
        string $license_key_option = '',
        string $license_status_option = '',
        ?int $product_id = null
    ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->api_url = rtrim($api_url, '/');
        $this->product_id = $product_id;

        // 如果未指定选项名称，则使用默认格式
        $slug = sanitize_title($plugin_name);
        $this->license_key_option = $license_key_option ?: "{$slug}_license_key";
        $this->license_status_option = $license_status_option ?: "{$slug}_license_status";
    }

    /**
     * 获取许可证密钥
     *
     * @return string 许可证密钥
     */
    public function get_license_key(): string
    {
        return get_option($this->license_key_option, '');
    }

    /**
     * 设置许可证密钥
     *
     * @param string $license_key 许可证密钥
     * @return bool 是否成功设置
     */
    public function set_license_key(string $license_key): bool
    {
        return update_option($this->license_key_option, $license_key);
    }

    /**
     * 获取许可证状态
     *
     * @return string 许可证状态
     */
    public function get_license_status(): string
    {
        return get_option($this->license_status_option, 'inactive');
    }

    /**
     * 设置许可证状态
     *
     * @param string $status 许可证状态
     * @return bool 是否成功设置
     */
    public function set_license_status(string $status): bool
    {
        return update_option($this->license_status_option, $status);
    }

    /**
     * 检查许可证是否激活
     *
     * @return bool 是否激活
     */
    public function is_license_active(): bool
    {
        return $this->get_license_status() === 'active';
    }

    /**
     * 验证许可证密钥
     *
     * @param string|null $license_key 许可证密钥，如果为 null 则使用保存的密钥
     * @return array 验证结果
     */
    public function validate_license(string $license_key = null): array
    {
        $license_key = $license_key ?: $this->get_license_key();

        if (empty($license_key)) {
            return [
                'success' => false,
                'error' => '许可证密钥不能为空',
            ];
        }

        // 构建请求参数
        $args = [
            'license_key' => $license_key,
            'site_url' => home_url(),
            'timestamp' => time(),
            'nonce' => wp_generate_password(16, false),
        ];

        if ($this->product_id) {
            $args['product_id'] = $this->product_id;
        }

        // 发送验证请求到许可证服务器
        $response = wp_remote_post($this->api_url . '/validate', [
            'body' => $args,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $this->plugin_name . '/' . $this->version,
            ],
        ]);

        // 检查请求是否成功
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => '无法连接到许可证服务器: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => '许可证服务器返回错误状态码: ' . $status_code,
            ];
        }

        // 解析响应数据
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => '无法解析服务器响应数据',
            ];
        }

        // 返回验证结果
        if (isset($data['success']) && $data['success']) {
            // 更新许可证状态
            $this->set_license_status('active');

            return [
                'success' => true,
                'license' => $data['license'] ?? [],
            ];
        } else {
            // 更新许可证状态
            $this->set_license_status('inactive');

            return [
                'success' => false,
                'error' => $data['message'] ?? '许可证验证失败',
            ];
        }
    }

    /**
     * 激活许可证
     *
     * @param string|null $license_key 许可证密钥，如果为 null 则使用保存的密钥
     * @return array 激活结果
     */
    public function activate_license(string $license_key = null): array
    {
        $license_key = $license_key ?: $this->get_license_key();

        if (empty($license_key)) {
            return [
                'success' => false,
                'error' => '许可证密钥不能为空',
            ];
        }

        // 保存许可证密钥
        $this->set_license_key($license_key);

        // 构建请求参数
        $args = [
            'license_key' => $license_key,
            'site_url' => home_url(),
            'timestamp' => time(),
            'nonce' => wp_generate_password(16, false),
        ];

        if ($this->product_id) {
            $args['product_id'] = $this->product_id;
        }

        // 发送激活请求到许可证服务器
        $response = wp_remote_post($this->api_url . '/activate', [
            'body' => $args,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $this->plugin_name . '/' . $this->version,
            ],
        ]);

        // 检查请求是否成功
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => '无法连接到许可证服务器: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => '许可证服务器返回错误状态码: ' . $status_code,
            ];
        }

        // 解析响应数据
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => '无法解析服务器响应数据',
            ];
        }

        // 返回激活结果
        if (isset($data['success']) && $data['success']) {
            // 更新许可证状态
            $this->set_license_status('active');

            return [
                'success' => true,
                'license' => $data['license'] ?? [],
            ];
        } else {
            // 更新许可证状态
            $this->set_license_status('inactive');

            return [
                'success' => false,
                'error' => $data['message'] ?? '许可证激活失败',
            ];
        }
    }

    /**
     * 停用许可证
     *
     * @return array 停用结果
     */
    public function deactivate_license(): array
    {
        $license_key = $this->get_license_key();

        if (empty($license_key)) {
            return [
                'success' => false,
                'error' => '许可证密钥不能为空',
            ];
        }

        // 构建请求参数
        $args = [
            'license_key' => $license_key,
            'site_url' => home_url(),
            'timestamp' => time(),
            'nonce' => wp_generate_password(16, false),
        ];

        if ($this->product_id) {
            $args['product_id'] = $this->product_id;
        }

        // 发送停用请求到许可证服务器
        $response = wp_remote_post($this->api_url . '/deactivate', [
            'body' => $args,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $this->plugin_name . '/' . $this->version,
            ],
        ]);

        // 检查请求是否成功
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => '无法连接到许可证服务器: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => '许可证服务器返回错误状态码: ' . $status_code,
            ];
        }

        // 解析响应数据
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => '无法解析服务器响应数据',
            ];
        }

        // 返回停用结果
        if (isset($data['success']) && $data['success']) {
            // 更新许可证状态
            $this->set_license_status('inactive');

            return [
                'success' => true,
                'message' => $data['message'] ?? '许可证已成功停用',
            ];
        } else {
            return [
                'success' => false,
                'error' => $data['message'] ?? '许可证停用失败',
            ];
        }
    }

    /**
     * 检查许可证状态
     *
     * @return array 检查结果
     */
    public function check_license_status(): array
    {
        $license_key = $this->get_license_key();

        if (empty($license_key)) {
            return [
                'success' => false,
                'error' => '许可证密钥不能为空',
            ];
        }

        // 构建请求参数
        $args = [
            'license_key' => $license_key,
            'site_url' => home_url(),
            'timestamp' => time(),
            'nonce' => wp_generate_password(16, false),
        ];

        if ($this->product_id) {
            $args['product_id'] = $this->product_id;
        }

        // 发送状态检查请求到许可证服务器
        $response = wp_remote_post($this->api_url . '/status', [
            'body' => $args,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $this->plugin_name . '/' . $this->version,
            ],
        ]);

        // 检查请求是否成功
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => '无法连接到许可证服务器: ' . $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => '许可证服务器返回错误状态码: ' . $status_code,
            ];
        }

        // 解析响应数据
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => '无法解析服务器响应数据',
            ];
        }

        // 返回状态检查结果
        if (isset($data['success']) && $data['success']) {
            // 更新许可证状态
            $license_status = $data['license']['status'] ?? 'inactive';
            $this->set_license_status($license_status === 'active' ? 'active' : 'inactive');

            return [
                'success' => true,
                'license' => $data['license'] ?? [],
                'status' => $license_status,
            ];
        } else {
            // 更新许可证状态
            $this->set_license_status('inactive');

            return [
                'success' => false,
                'error' => $data['message'] ?? '许可证状态检查失败',
            ];
        }
    }
}
