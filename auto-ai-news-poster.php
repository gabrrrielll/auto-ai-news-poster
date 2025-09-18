<?php

/**
 * Plugin Name: Auto AI News Poster
 * Description: Un plugin care preia știri de pe minim trei surse, le analizează și publică un articol obiectiv în mod automat sau manual.
 * Version: 1.7
 * Author: Gabriel Sandu
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include fișierele necesare
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-api.php'; 
require_once plugin_dir_path(__FILE__) . 'includes/class-auto-ai-news-poster-hooks.php';

// Funcția pentru înregistrarea scripturilor și stilurilor
add_action('admin_enqueue_scripts', 'auto_ai_news_poster_enqueue_scripts');
function auto_ai_news_poster_enqueue_scripts($hook_suffix)
{
    // Verificăm dacă ne aflăm pe pagina de setări a pluginului sau pe pagina de editare a unui articol
    if ($hook_suffix === 'post.php' || $hook_suffix === 'post-new.php' || (isset($_GET['page']) && $_GET['page'] === 'auto-ai-news-poster')) {

        // NU mai încărcăm fișiere externe pentru a evita problemele MIME type
        // Totul este încorporat inline în funcția auto_ai_news_poster_fix_css_mime_type()
        
        // Doar localizăm variabilele pentru AJAX (fără script extern)
        wp_localize_script('jquery', 'autoAiNewsPosterAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('get_article_from_sources_nonce'),
        ]);
    }
}

// Fix pentru problema MIME type cu CSS-ul și JavaScript-ul
add_action('admin_head', 'auto_ai_news_poster_fix_css_mime_type', 1);
function auto_ai_news_poster_fix_css_mime_type() {
    // Verificăm dacă suntem pe pagina de setări sau pe pagina de editare articol
    $is_settings_page = isset($_GET['page']) && $_GET['page'] === 'auto-ai-news-poster';
    $is_post_page = (isset($_GET['post']) && $_GET['post']) || (isset($_GET['post_type']) && $_GET['post_type'] === 'post');
    
    if ($is_settings_page || $is_post_page) {
        echo '<style type="text/css">
        /* Auto AI News Poster - Complete styles inline to avoid MIME type issues */
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease-in-out;
        }
        
        .auto-ai-news-poster-admin {
            background: var(--background-color);
            min-height: 100vh;
            padding: 20px;
        }
        
        .auto-ai-news-poster-admin .wrap {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--card-background);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .auto-ai-news-poster-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .auto-ai-news-poster-header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.05\'%3E%3Ccircle cx=\'30\' cy=\'30\' r=\'2\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            opacity: 0.1;
        }
        
        .auto-ai-news-poster-header h1 {
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .auto-ai-news-poster-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .auto-ai-news-poster-form {
            padding: 30px;
        }
        
        .settings-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .settings-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .settings-card-header {
            background: var(--background-color);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .settings-card-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .settings-card-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .settings-card-content {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .control-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .form-description {
            margin-top: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin: 10px 0;
        }
        
        .alert-danger {
            background: #fee;
            border: 1px solid #fcc;
            color: #721c24;
        }
        
        .info-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 16px;
            font-size: 12px;
            margin-left: 8px;
            cursor: help;
        }
        
        .tooltip {
            position: relative;
        }
        
        .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: var(--text-primary);
            color: white;
            text-align: center;
            border-radius: var(--border-radius);
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .api-instructions {
            margin-top: 20px;
            padding: 15px;
            background: var(--background-color);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .api-instructions-toggle {
            cursor: pointer;
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .toggle-icon {
            transition: transform 0.3s;
        }
        
        .api-instructions-content {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .api-instructions-content ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .api-instructions-content li {
            margin-bottom: 8px;
        }
        
        .api-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 12px;
            margin-top: 15px;
        }
        
        .api-warning strong {
            color: #856404;
        }
        
        .api-warning ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }
        
        .api-warning li {
            margin-bottom: 4px;
            color: #856404;
        }
        
        /* Metabox styles for post editing page */
        .auto-ai-news-poster-metabox {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .auto-ai-news-poster-metabox .postbox-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            padding: 15px 20px;
            border-bottom: none;
        }
        
        .auto-ai-news-poster-metabox .postbox-header h2 {
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .auto-ai-news-poster-metabox .inside {
            padding: 20px;
            margin: 0;
        }
        
        .metabox-section {
            margin-bottom: 25px;
            padding: 15px;
            background: var(--background-color);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }
        
        .metabox-section:last-child {
            margin-bottom: 0;
        }
        
        .metabox-section-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .metabox-section-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }
        
        .metabox-section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .metabox-textarea {
            width: 100%;
            min-height: 80px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            transition: var(--transition);
        }
        
        .metabox-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .metabox-generate-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .metabox-generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .metabox-generate-btn:active {
            transform: translateY(0);
        }
        
        .metabox-generate-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .auto-ai-news-poster-admin {
                padding: 10px;
            }
            
            .auto-ai-news-poster-form {
                padding: 20px;
            }
            
            .auto-ai-news-poster-header h1 {
                font-size: 2rem;
            }
            
            .metabox-section {
                padding: 12px;
            }
            
            .metabox-generate-btn {
                width: 100%;
                justify-content: center;
            }
        }
        </style>';
        
        // Adăugăm și JavaScript-ul inline pentru a evita problemele MIME type
        echo '<script type="text/javascript">
        function toggleApiInstructions() {
            const content = document.getElementById("api-instructions-content");
            const icon = document.querySelector(".toggle-icon");
            
            if (content.style.display === "none") {
                content.style.display = "block";
                icon.textContent = "▲";
            } else {
                content.style.display = "none";
                icon.textContent = "▼";
            }
        }
        
        function refreshModelsList() {
            const apiKey = document.getElementById("chatgpt_api_key").value;
            
            if (!apiKey) {
                alert("Vă rugăm să introduceți mai întâi cheia API OpenAI.");
                return;
            }
            
            const refreshBtn = document.querySelector("button[onclick=\'refreshModelsList()\']");
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = "⏳ Se încarcă...";
            refreshBtn.disabled = true;
            
            fetch("' . admin_url('admin-ajax.php') . '", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    action: "refresh_openai_models",
                    api_key: apiKey,
                    nonce: "' . wp_create_nonce('refresh_models_nonce') . '"
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("Eroare la actualizarea listei de modele: " + (data.data || "Eroare necunoscută"));
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Eroare la actualizarea listei de modele.");
            })
            .finally(() => {
                refreshBtn.innerHTML = originalText;
                refreshBtn.disabled = false;
            });
        }
        </script>';
    }
}

// CSS-ul este încorporat inline pentru a evita problemele MIME type
// Nu mai încărcăm fișiere externe
