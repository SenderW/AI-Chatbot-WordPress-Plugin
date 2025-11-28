<?php
/**
 * Plugin Name: AI Chatbot
 * Description: AI-powered chatbot with WordPress integration and knowledge base
 * Version: 1.1
 * Author: Your Name or Company
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIChatbotPlugin {
    private $options;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_chatbot_knowledge_base';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_chatbot'));
        add_action('wp_ajax_ai_chatbot_request', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nopriv_ai_chatbot_request', array($this, 'handle_chat_request'));
        add_action('wp_ajax_ai_chatbot_upload_knowledge', array($this, 'handle_knowledge_upload'));
        add_action('wp_ajax_ai_chatbot_delete_knowledge', array($this, 'handle_knowledge_delete'));
        add_action('wp_ajax_ai_chatbot_list_knowledge', array($this, 'handle_knowledge_list'));
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_knowledge_base_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        register_activation_hook(__FILE__, array($this, 'create_knowledge_table'));
        
        // Default options
        $defaults = array(
            'deepseek_api_key' => '',
            'welcome_message' => 'Hi! How can I help you today?',
            'search_keywords_url' => '',
            'send_button_text' => 'Send',
            'input_placeholder' => 'Type your message...',
            'chat_title' => 'Chat',
            'error_message' => 'Sorry, something went wrong.',
            'fallback_posts_intro' => 'These resources might help:',
            'more_info_intro' => 'Here you can find more information:',
            'contact_fallback_prefix' => 'Please contact us by email at: ',
            'chatbot_enabled' => true,
            'fallback_email' => 'support@example.com'
        );
        
        $this->options = array_merge($defaults, get_option('ai_chatbot_options', array()));
    }
    
    public function init() {
        // Plugin initialization
    }
    
    public function create_knowledge_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            file_hash varchar(64) NOT NULL,
            file_size bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            content longtext NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY file_hash (file_hash)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    public function add_admin_menu() {
        add_options_page(
            'AI Chatbot Settings',
            'AI Chatbot',
            'manage_options',
            'ai-chatbot',
            array($this, 'options_page')
        );
        
        add_menu_page(
            'AI Chatbot Knowledge Base',
            'AI Chatbot KB',
            'manage_options',
            'ai-chatbot-knowledge-base',
            array($this, 'knowledge_base_page'),
            'dashicons-format-chat',
            81
        );
    }
    
    public function settings_init() {
        register_setting('ai_chatbot', 'ai_chatbot_options');
        
        add_settings_section(
            'ai_chatbot_section',
            'AI Chatbot Configuration',
            null,
            'ai_chatbot'
        );
        
        add_settings_field(
            'deepseek_api_key',
            'DeepSeek API Key',
            array($this, 'render_deepseek_api_key_field'),
            'ai_chatbot',
            'ai_chatbot_section'
        );
        
        add_settings_field(
            'welcome_message',
            'Welcome Message',
            array($this, 'render_welcome_message_field'),
            'ai_chatbot',
            'ai_chatbot_section'
        );
        
        add_settings_field(
            'fallback_email',
            'Fallback Email Address',
            array($this, 'render_fallback_email_field'),
            'ai_chatbot',
            'ai_chatbot_section'
        );
        
        add_settings_field(
            'contact_fallback_prefix',
            'Contact Prefix (for fallback message)',
            array($this, 'render_contact_prefix_field'),
            'ai_chatbot',
            'ai_chatbot_section'
        );
        
        add_settings_field(
            'chatbot_enabled',
            'Enable Chatbot',
            array($this, 'render_chatbot_enabled_field'),
            'ai_chatbot',
            'ai_chatbot_section'
        );
    }
    
    public function render_deepseek_api_key_field() {
        $value = $this->options['deepseek_api_key'] ?? '';
        echo "<input type='password' name='ai_chatbot_options[deepseek_api_key]' value='" . esc_attr($value) . "' style='width: 400px;'>";
        echo "<p class='description'>Store your DeepSeek API key here. Do not hard-code it in the plugin file.</p>";
    }
    
    public function render_welcome_message_field() {
        $value = $this->options['welcome_message'] ?? 'Hi! How can I help you today?';
        echo "<input type='text' name='ai_chatbot_options[welcome_message]' value='" . esc_attr($value) . "' style='width: 400px;'>";
    }
    
    public function render_fallback_email_field() {
        $value = $this->options['fallback_email'] ?? 'support@example.com';
        echo "<input type='email' name='ai_chatbot_options[fallback_email]' value='" . esc_attr($value) . "' style='width: 400px;'>";
        echo "<p class='description'>Email address shown when the chatbot cannot answer. This address is also used for privacy-safe knowledge base handling.</p>";
    }
    
    public function render_contact_prefix_field() {
        $value = $this->options['contact_fallback_prefix'] ?? 'Please contact us by email at: ';
        echo "<input type='text' name='ai_chatbot_options[contact_fallback_prefix]' value='" . esc_attr($value) . "' style='width: 400px;'>";
        echo "<p class='description'>Prefix used in the fallback sentence the AI can return.</p>";
    }
    
    public function render_chatbot_enabled_field() {
        $checked = !empty($this->options['chatbot_enabled']) ? 'checked' : '';
        echo "<input type='checkbox' name='ai_chatbot_options[chatbot_enabled]' value='1' $checked>";
        echo "<p class='description'>If disabled, the chatbot will not be shown on the frontend.</p>";
    }
    
    public function enqueue_scripts() {
        if (is_admin()) {
            return;
        }
        
        wp_enqueue_style('ai-chatbot-style', plugin_dir_url(__FILE__) . 'css/ai-chatbot.css');
        wp_enqueue_script('ai-chatbot-script', plugin_dir_url(__FILE__) . 'js/ai-chatbot.js', array('jquery'), null, true);
        
        wp_localize_script('ai-chatbot-script', 'AIChatbot', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_nonce'),
            'welcome_message' => $this->options['welcome_message'] ?? 'Hi! How can I help you today?',
            'send_button_text' => $this->options['send_button_text'] ?? 'Send',
            'input_placeholder' => $this->options['input_placeholder'] ?? 'Type your message...',
            'chat_title' => $this->options['chat_title'] ?? 'Chat',
            'error_message' => $this->options['error_message'] ?? 'Sorry, something went wrong.',
            'fallback_posts_intro' => $this->options['fallback_posts_intro'] ?? 'These resources might help:',
            'chatbot_enabled' => !empty($this->options['chatbot_enabled'])
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_ai-chatbot-knowledge-base') {
            return;
        }
        
        wp_enqueue_style('ai-chatbot-admin-style', plugin_dir_url(__FILE__) . 'css/ai-chatbot-admin.css');
        wp_enqueue_script('ai-chatbot-admin-script', plugin_dir_url(__FILE__) . 'js/ai-chatbot-admin.js', array('jquery'), null, true);
        
        wp_localize_script('ai-chatbot-admin-script', 'AIChatbotAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_chatbot_admin_nonce')
        ));
    }
    
    public function render_chatbot() {
        if (is_admin() || empty($this->options['chatbot_enabled'])) {
            return;
        }
        
        ?>
        <div id="ai-chatbot-container">
            <div id="ai-chatbot-header">
                <span id="ai-chatbot-title"><?php echo esc_html($this->options['chat_title'] ?? 'Chat'); ?></span>
                <div id="ai-chatbot-controls">
                    <button id="ai-chatbot-minimize">−</button>
                    <button id="ai-chatbot-close">×</button>
                </div>
            </div>
            <div id="ai-chatbot-messages"></div>
            <div id="ai-chatbot-input-area">
                <input type="text" id="ai-chatbot-input" placeholder="<?php echo esc_attr($this->options['input_placeholder'] ?? 'Type your message...'); ?>">
                <button id="ai-chatbot-send"><?php echo esc_html($this->options['send_button_text'] ?? 'Send'); ?></button>
            </div>
        </div>
        
        <button id="ai-chatbot-toggle" aria-label="Open chat">
            💬
        </button>
        
        <style>
            #ai-chatbot-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 320px;
                max-height: 480px;
                background: #ffffff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                display: flex;
                flex-direction: column;
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                z-index: 9999;
            }
            
            #ai-chatbot-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 10px;
                background: #1f2933;
                color: #ffffff;
                border-radius: 8px 8px 0 0;
                font-size: 14px;
            }
            
            #ai-chatbot-controls button {
                background: transparent;
                border: none;
                color: #ffffff;
                cursor: pointer;
                font-size: 14px;
                margin-left: 4px;
            }
            
            #ai-chatbot-messages {
                flex: 1;
                padding: 10px;
                overflow-y: auto;
                font-size: 13px;
                line-height: 1.4;
            }
            
            #ai-chatbot-input-area {
                border-top: 1px solid #e0e0e0;
                padding: 8px;
                display: flex;
                gap: 6px;
            }
            
            #ai-chatbot-input {
                flex: 1;
                padding: 6px 8px;
                border-radius: 4px;
                border: 1px solid #d0d0d0;
                font-size: 13px;
            }
            
            #ai-chatbot-send {
                background: #2563eb;
                color: #ffffff;
                border: none;
                border-radius: 4px;
                padding: 6px 10px;
                cursor: pointer;
                font-size: 13px;
            }
            
            #ai-chatbot-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 48px;
                height: 48px;
                border-radius: 999px;
                border: none;
                background: #2563eb;
                color: #ffffff;
                font-size: 22px;
                display: none;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                z-index: 9998;
            }
            
            @media (max-width: 768px) {
                #ai-chatbot-container {
                    display: none;
                    width: 100%;
                    max-width: 100%;
                    max-height: 80vh;
                    right: 0;
                    bottom: 0;
                    border-radius: 8px 8px 0 0;
                }
                
                #ai-chatbot-toggle {
                    display: flex;
                }
            }
        </style>
        
        <script>
            (function() {
                var container = document.getElementById('ai-chatbot-container');
                var toggle = document.getElementById('ai-chatbot-toggle');
                var minimize = document.getElementById('ai-chatbot-minimize');
                var closeBtn = document.getElementById('ai-chatbot-close');
                var messages = document.getElementById('ai-chatbot-messages');
                var input = document.getElementById('ai-chatbot-input');
                var send = document.getElementById('ai-chatbot-send');
                
                function addMessage(text, sender) {
                    var div = document.createElement('div');
                    div.className = 'ai-chatbot-message ai-chatbot-message-' + sender;
                    div.textContent = text;
                    messages.appendChild(div);
                    messages.scrollTop = messages.scrollHeight;
                }
                
                function addPosts(posts) {
                    if (!posts || !posts.length) return;
                    
                    var intro = document.createElement('div');
                    intro.className = 'ai-chatbot-posts-intro';
                    intro.textContent = AIChatbot.fallback_posts_intro || 'These resources might help:';
                    messages.appendChild(intro);
                    
                    posts.forEach(function(post) {
                        var btn = document.createElement('a');
                        btn.className = 'ai-chatbot-post-button';
                        btn.href = post.url;
                        btn.target = '_blank';
                        btn.textContent = post.title;
                        messages.appendChild(btn);
                    });
                    
                    messages.scrollTop = messages.scrollHeight;
                }
                
                function sendMessage() {
                    var text = input.value.trim();
                    if (!text) return;
                    
                    addMessage(text, 'user');
                    input.value = '';
                    
                    addMessage('Thinking …', 'bot');
                    
                    jQuery.post(AIChatbot.ajax_url, {
                        action: 'ai_chatbot_request',
                        nonce: AIChatbot.nonce,
                        message: text
                    }).done(function(response) {
                        messages.removeChild(messages.lastChild);
                        
                        if (response && response.success && response.data) {
                            if (response.data.answer) {
                                addMessage(response.data.answer, 'bot');
                            }
                            if (response.data.posts) {
                                addPosts(response.data.posts);
                            }
                        } else {
                            addMessage(AIChatbot.error_message || 'Sorry, something went wrong.', 'bot');
                        }
                    }).fail(function() {
                        messages.removeChild(messages.lastChild);
                        addMessage(AIChatbot.error_message || 'Sorry, something went wrong.', 'bot');
                    });
                }
                
                if (!AIChatbot.chatbot_enabled) {
                    container.style.display = 'none';
                    if (toggle) {
                        toggle.style.display = 'none';
                    }
                    return;
                }
                
                if (window.innerWidth > 768) {
                    container.style.display = 'flex';
                    toggle.style.display = 'none';
                } else {
                    container.style.display = 'none';
                    toggle.style.display = 'flex';
                }
                
                addMessage(AIChatbot.welcome_message || 'Hi! How can I help you today?', 'bot');
                
                send.addEventListener('click', sendMessage);
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        sendMessage();
                    }
                });
                
                minimize.addEventListener('click', function() {
                    if (container.style.display === 'flex') {
                        container.style.display = 'none';
                        toggle.style.display = 'flex';
                    }
                });
                
                closeBtn.addEventListener('click', function() {
                    container.style.display = 'none';
                    toggle.style.display = 'flex';
                });
                
                toggle.addEventListener('click', function() {
                    container.style.display = 'flex';
                    toggle.style.display = 'none';
                });
                
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        container.style.display = 'flex';
                        toggle.style.display = 'none';
                    } else {
                        container.style.display = 'none';
                        toggle.style.display = 'flex';
                    }
                });
            })();
        </script>
        <?php
    }
    
    public function options_page() {
        ?>
        <div class="wrap">
            <h1>AI Chatbot Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('ai_chatbot');
                do_settings_sections('ai_chatbot');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function knowledge_base_page() {
        ?>
        <div class="wrap">
            <h1>AI Chatbot Knowledge Base</h1>
            <p>Upload text files to build a private knowledge base for the chatbot. Basic personal data patterns are removed before content is stored.</p>
            
            <form id="ai-chatbot-knowledge-upload-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="knowledge-file">Upload Files</label></th>
                        <td>
                            <input type="file" id="knowledge-file" accept=".txt,.md" multiple>
                            <button type="button" id="upload-knowledge" class="button">Upload</button>
                            <p class="description">Upload .txt or .md files (max 5MB each)</p>
                        </td>
                    </tr>
                </table>
            </form>
            
            <h3>Current Knowledge Base</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Size (KB)</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ai-chatbot-knowledge-list">
                    <tr>
                        <td colspan="4">Loading …</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function handle_knowledge_upload() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload error');
        }
        
        $allowed_types = array('text/plain', 'text/markdown', 'text/x-markdown', 'text/markdown; charset=utf-8');
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file_type['type'], $allowed_types)) {
            wp_send_json_error('Only .txt and .md files are allowed');
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('File too large (max 5MB)');
        }
        
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || trim($content) === '') {
            wp_send_json_error('File is empty or could not be read');
        }
        
        $content = $this->sanitize_knowledge_content($content);
        
        if (trim($content) === '') {
            wp_send_json_error('File content is empty after sanitization');
        }
        
        $file_hash = hash('sha256', $content);
        
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE file_hash = %s",
            $file_hash
        ));
        
        if ($existing > 0) {
            wp_send_json_error('An identical file is already stored in the knowledge base');
        }
        
        $wpdb->insert(
            $this->table_name,
            array(
                'file_name' => sanitize_file_name($file['name']),
                'file_hash' => $file_hash,
                'file_size' => (int)$file['size'],
                'content' => wp_kses_post($content)
            ),
            array('%s', '%s', '%d', '%s')
        );
        
        if ($wpdb->last_error) {
            wp_send_json_error('Database error while saving knowledge base entry');
        }
        
        wp_send_json_success('File uploaded and stored in knowledge base');
    }
    
    public function handle_knowledge_delete() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error('Invalid ID');
        }
        
        global $wpdb;
        $deleted = $wpdb->delete($this->table_name, array('id' => $id), array('%d'));
        
        if ($deleted === false) {
            wp_send_json_error('Database error while deleting entry');
        }
        
        wp_send_json_success('Entry deleted');
    }
    
    public function handle_knowledge_list() {
        check_ajax_referer('ai_chatbot_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        
        $entries = $wpdb->get_results("SELECT id, file_name, file_size, created_at FROM {$this->table_name} ORDER BY created_at DESC");
        
        $data = array();
        foreach ($entries as $entry) {
            $data[] = array(
                'id' => (int)$entry->id,
                'file_name' => $entry->file_name,
                'file_size_kb' => round($entry->file_size / 1024),
                'created_at' => $entry->created_at
            );
        }
        
        wp_send_json_success($data);
    }
    
    private function sanitize_knowledge_content($content) {
        $fallback_email = $this->options['fallback_email'] ?? 'support@example.com';
        
        $content = str_replace($fallback_email, '[BUSINESS_EMAIL_PROTECTED]', $content);
        
        $patterns = array(
            '/[A-ZÄÖÜ][a-zäöüß]+ [A-ZÄÖÜ][a-zäöüß]+/',
            '/\b\d{3,4} ?[A-Za-zäöüÄÖÜß]{0,3} ?\/? ?\d{3,5}\b/',
            '/\b\d{5}\b/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
            '/\b\d{2}\.\d{2}\.\d{4}\b/',
            '/\b\d{1,2}\.\d{1,2}\.\d{2}\b/'
        );
        
        $content = preg_replace($patterns, '[REMOVED]', $content);
        
        $content = str_replace('[BUSINESS_EMAIL_PROTECTED]', $fallback_email, $content);
        
        return $content;
    }
    
    public function handle_chat_request() {
        check_ajax_referer('ai_chatbot_nonce', 'nonce');
        
        $api_key = $this->options['deepseek_api_key'] ?? '';
        if (empty($api_key)) {
            wp_send_json_error('DeepSeek API key is not configured');
        }
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        if (empty($message)) {
            wp_send_json_error('Empty message');
        }
        
        if (!$this->check_gdpr_compliance()) {
            wp_send_json_error('GDPR compliance requirements not met');
        }
        
        $knowledge_context = $this->get_knowledge_context($message);
        $posts = $this->get_relevant_posts($message);
        
        $posts_context = "Website Posts:\n";
        foreach ($posts as $post) {
            $posts_context .= "Title: " . $post->post_title . "\n";
            $posts_context .= "Content: " . wp_strip_all_tags(substr($post->post_content, 0, 300)) . "...\n";
            $posts_context .= "URL: " . get_permalink($post->ID) . "\n\n";
        }
        
        $system_message = "You are a helpful assistant. Provide very short, precise answers (maximum 50 words). Use simple everyday language.\n\n";
        
        if (!empty($knowledge_context)) {
            $system_message .= "KNOWLEDGE BASE (use this FIRST for your answer):\n" . $knowledge_context;
            $system_message .= "\n\nIf the knowledge base does not contain enough information, you can also use these website posts:\n" . $posts_context;
        } else {
            $system_message .= "Website Posts:\n" . $posts_context;
        }
        
        $fallback_email = $this->options['fallback_email'] ?? 'support@example.com';
        $contact_prefix = $this->options['contact_fallback_prefix'] ?? 'Please contact us by email at: ';
        $system_message .= "\n\nIf you cannot answer the question from the knowledge base or posts, reply with this exact sentence: '" . $contact_prefix . $fallback_email . "'. This email address is allowed and should be displayed.";
        
        $response = $this->call_deepseek_api($message, $system_message, $api_key);
        
        if ($response) {
            $suggested_posts = $this->get_suggested_posts($message, $posts);
            
            $show_posts = empty($knowledge_context) || stripos($response, $fallback_email) !== false;
            
            if (empty($knowledge_context)) {
                $keyword = $this->extract_search_keyword($message, $api_key);
                if ($keyword) {
                    $search_posts = $this->search_wordpress_posts_by_keyword($keyword);
                    if (!empty($search_posts)) {
                        $more_info_intro = $this->options['more_info_intro'] ?? 'Here you can find more information:';
                        $response .= "\n\n" . $more_info_intro;
                        foreach ($search_posts as $post) {
                            $response .= "\n- " . $post->post_title . " (" . get_permalink($post->ID) . ")";
                        }
                    }
                }
            }
            
            $data = array(
                'answer' => $response
            );
            
            if ($show_posts && !empty($suggested_posts)) {
                $data['posts'] = $suggested_posts;
            }
            
            wp_send_json_success($data);
        } else {
            wp_send_json_error('No response from DeepSeek');
        }
    }
    
    private function check_gdpr_compliance() {
        $privacy_policy_page_id = (int)get_option('wp_page_for_privacy_policy');
        if ($privacy_policy_page_id <= 0) {
            return false;
        }
        
        return true;
    }
    
    private function get_knowledge_context($message) {
        global $wpdb;
        
        $entries = $wpdb->get_results("SELECT content FROM {$this->table_name} ORDER BY created_at DESC LIMIT 10");
        
        if (empty($entries)) {
            return '';
        }
        
        $context = '';
        foreach ($entries as $entry) {
            $context .= $entry->content . "\n\n";
            if (strlen($context) > 8000) {
                break;
            }
        }
        
        return $context;
    }
    
    private function get_relevant_posts($message) {
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 5,
            's' => $message,
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    private function get_suggested_posts($message, $posts) {
        $suggested = array();
        $message_lower = strtolower($message);
        
        foreach ($posts as $post) {
            $title = $post->post_title;
            $title_lower = strtolower($title);
            
            similar_text($message_lower, $title_lower, $percent);
            
            if ($percent > 20) {
                $suggested[] = array(
                    'title' => mb_strlen($title) > 40 ? mb_substr($title, 0, 37) . '...' : $title,
                    'url' => get_permalink($post->ID)
                );
            }
        }
        
        return array_slice($suggested, 0, 3);
    }
    
    private function call_deepseek_api($message, $context, $api_key) {
        $data = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array('role' => 'system', 'content' => $context),
                array('role' => 'user', 'content' => $message)
            ),
            'max_tokens' => 80,
            'temperature' => 0.3
        );
        
        $response = wp_remote_post('https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            return null;
        }
        
        return trim($result['choices'][0]['message']['content']);
    }
    
    private function extract_search_keyword($message, $api_key) {
        $data = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Extract the main topic from the user request. Answer only with a single keyword or very short phrase, without any further explanation.'
                ),
                array(
                    'role' => 'user',
                    'content' => $message
                )
            ),
            'max_tokens' => 5,
            'temperature' => 0.0
        );
        
        $response = wp_remote_post('https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($data),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            return null;
        }
        
        return trim($result['choices'][0]['message']['content']);
    }
    
    private function search_wordpress_posts_by_keyword($keyword) {
        if (empty($keyword)) {
            return array();
        }
        
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 3,
            's' => $keyword,
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        return $query->posts;
    }
}

new AIChatbotPlugin();
