# AI Chatbot WordPress Plugin

An AI powered chatbot for WordPress that answers visitor questions based on your site content and an optional private knowledge base, using the DeepSeek chat API.

## Features

- Short, focused answers powered by a DeepSeek chat model  
- Uses your WordPress posts as context and can show them as clickable buttons  
- Optional private knowledge base from uploaded `.txt` and `.md` files  
- Responsive widget that works on desktop, tablet and mobile  
- Auto open on larger screens and a floating chat button on mobile  
- Simple settings page for API key, welcome message and fallback email  
- Basic GDPR aware handling of uploaded files with simple pattern based sanitization  

> This plugin does not include any API key. You must provide your own DeepSeek API key in the settings.

---

## Files and folder structure

Place the plugin files in a folder named for example `ai-chatbot-plugin` inside `wp-content/plugins`.

Recommended structure:

```text
ai-chatbot-plugin/
  ai-chatbot-plugin.php        (main plugin file, contains PHP logic and frontend widget)
  README.md                    (this documentation)

  css/
    ai-chatbot.css             (optional extra styles, currently not strictly required)
    ai-chatbot-admin.css       (admin styles for the knowledge base page)

  js/
    ai-chatbot.js              (optional extra logic for the frontend widget)
    ai-chatbot-admin.js        (JavaScript for knowledge base upload and list handling)
