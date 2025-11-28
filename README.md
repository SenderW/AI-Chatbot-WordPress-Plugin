# AI Chatbot WordPress Plugin

An AI-powered chatbot for WordPress that answers visitor questions based on your site content and an optional private knowledge base, using the DeepSeek chat API.

## Features

- Short, focused answers powered by a DeepSeek chat model
- Uses your WordPress posts as context and can show them as clickable buttons
- Optional private knowledge base from uploaded `.txt` and `.md` files
- Responsive widget that works on desktop, tablet, and mobile
- Auto open on larger screens, floating chat button on mobile
- Simple settings page for API key, welcome message, and fallback email
- Basic GDPR aware handling of uploaded files with simple pattern based sanitization

> Note: This plugin does not ship with any API key. You must provide your own DeepSeek API key in the settings.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A DeepSeek API key with access to the chat endpoint
- A configured WordPress privacy policy page

## Installation

1. Copy the plugin folder (for example `ai-chatbot-plugin`) into your WordPress installation under:

   ```text
   /wp-content/plugins/
