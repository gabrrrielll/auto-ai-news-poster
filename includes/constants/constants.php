<?php

// Option Keys
const AUTO_AI_NEWS_POSTER_SETTINGS_OPTION = 'auto_ai_news_poster_settings';
const AUTO_AI_NEWS_POSTER_CURRENT_CATEGORY_INDEX = 'auto_ai_news_poster_current_category_index';
const AUTO_AI_NEWS_POSTER_LAST_POST_TIME = 'auto_ai_news_poster_last_post_time';

// Admin Page & Settings Group
const AUTO_AI_NEWS_POSTER_SETTINGS_PAGE = 'auto_ai_news_poster_settings_page';
const AUTO_AI_NEWS_POSTER_SETTINGS_GROUP = 'auto_ai_news_poster_settings_group';

// Transient Keys
const TRANSIENT_OPENAI_MODELS_CACHE = 'openai_models_cache';
const TRANSIENT_GEMINI_MODELS_CACHE = 'gemini_models_cache';
const TRANSIENT_DEEPSEEK_MODELS_CACHE = 'deepseek_models_cache';
const TRANSIENT_VERTEX_AI_TOKEN_PREFIX = 'vertex_ai_token_';

// API URLs
const URL_API_OPENAI_CHAT = 'https://api.openai.com/v1/chat/completions';
const URL_API_OPENAI_IMAGE = 'https://api.openai.com/v1/images/generations';
const URL_API_OPENAI_MODELS = 'https://api.openai.com/v1/models';

const URL_API_DEEPSEEK_CHAT = 'https://api.deepseek.com/chat/completions';
const URL_API_DEEPSEEK_MODELS = 'https://api.deepseek.com/models';

const URL_API_GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
const URL_API_GEMINI_MODELS = 'https://generativelanguage.googleapis.com/v1beta/models';
const URL_API_GEMINI_IMAGEN_4 = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-4:generateImages';

const URL_API_VERTEX_BASE = 'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:predict';

// Google OAuth
const URL_GOOGLE_OAUTH_TOKEN = 'https://oauth2.googleapis.com/token';
const URL_GOOGLE_CLOUD_PLATFORM_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

// Defaults
const DEFAULT_AI_MODEL = 'gpt-4o';
const DEFAULT_GEMINI_MODEL = 'gemini-1.5-pro';
const DEFAULT_TIMEOUT_SECONDS = 300;
const DEFAULT_IMAGE_TIMEOUT_SECONDS = 120;
