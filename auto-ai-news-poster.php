<?php

// TEMPORARY: Force PHP error logging for debugging (START)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', plugin_dir_path(__FILE__) . 'php_error.log');
// TEMPORARY: Force PHP error logging for debugging (END)

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

// Nu mai încărcăm scripturi externe - totul este inline
// Eliminăm complet această funcționalitate pentru a evita conflictele

// Fix pentru problema MIME type cu CSS-ul și JavaScript-ul
add_action('admin_head', 'auto_ai_news_poster_fix_css_mime_type', 1);
function auto_ai_news_poster_fix_css_mime_type() {
    // Verificăm dacă suntem pe pagina de setări sau pe pagina de editare articol
    $is_settings_page = isset($_GET['page']) && $_GET['page'] === 'auto-ai-news-poster';
    $is_post_page = (isset($_GET['post']) && $_GET['post']) || (isset($_GET['post_type']) && $_GET['post_type'] === 'post');
    
    // Debug pentru încărcarea CSS/JS
    error_log('🎨 CSS/JS Loading check:');
    error_log('   - Current URL: ' . $_SERVER['REQUEST_URI']);
    error_log('   - GET params: ' . print_r($_GET, true));
    error_log('   - Is settings page: ' . ($is_settings_page ? 'YES' : 'NO'));
    error_log('   - Is post page: ' . ($is_post_page ? 'YES' : 'NO'));
    error_log('   - Should load CSS/JS: ' . (($is_settings_page || $is_post_page) ? 'YES' : 'NO'));
    
    // TEMPORAR: Forțăm încărcarea pe toate paginile de admin pentru debug
    if (is_admin()) {
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

        /* Stiluri pentru Metaboxuri */
        .auto-ai-news-poster-metabox .inside {
            margin: 0;
            padding: 0;
        }

        .metabox-section {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            overflow: hidden;
            background: var(--background-color-secondary);
        }

        .metabox-section-header {
            background: var(--background-color);
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }

        .metabox-section-icon {
            font-size: 1.2rem;
            margin-right: 8px;
            color: var(--primary-color);
        }

        .metabox-section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .metabox-field-group {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .metabox-field-group:last-child {
            border-bottom: none;
        }

        .metabox-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .metabox-label .metabox-icon {
            margin-right: 5px;
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .metabox-input[type="text"],
        .metabox-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--input-background);
            color: var(--text-primary);
            transition: var(--transition);
            box-sizing: border-box; /* Include padding in element's total width and height */
        }

        .metabox-input[type="text"]:focus,
        .metabox-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .metabox-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .metabox-description {
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .metabox-generate-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: calc(100% - 30px); /* 30px = 2 * 15px padding */
            padding: 12px 15px;
            margin: 15px;
            background: linear-gradient(145deg, var(--primary-color), var(--primary-hover));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-small);
        }

        .metabox-generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            filter: brightness(1.1);
        }

        .metabox-generate-btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow-small);
        }

        .metabox-generate-btn span {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        /* Stiluri pentru buttton generare imagine AI */
        #generate-image-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: calc(100% - 30px); /* 30px = 2 * 15px padding */
            padding: 12px 15px;
            margin: 15px;
            background: linear-gradient(145deg, #6c5ce7, #8e44ad); /* O culoare diferită pentru butonul de imagine */
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-small);
        }

        #generate-image-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            filter: brightness(1.1);
        }

        #generate-image-button:active {
            transform: translateY(0);
            box-shadow: var(--shadow-small);
        }

        #generate-image-button .button-icon {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        /* Stiluri pentru câmpurile de input/textarea în metabox-ul de imagine */
        .auto-ai-metabox-content .metabox-input,
        .auto-ai-metabox-content .metabox-textarea {
            background-color: var(--input-background);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .auto-ai-metabox-content .metabox-input::placeholder,
        .auto-ai-metabox-content .metabox-textarea::placeholder {
            color: var(--text-secondary-light);
        }

        .auto-ai-metabox-content .metabox-description {
            color: var(--text-secondary);
        }
        </style>';
        
        // Adăugăm și JavaScript-ul inline pentru a evita problemele MIME type
        echo '<script type="text/javascript">
        var autoAiNewsPosterAjax = {
            ajax_url: "' . admin_url('admin-ajax.php') . '",
            generate_image_nonce: "' . wp_create_nonce('generate_image_nonce') . '"
        };
        // Funcții pentru pagina de setări
        function toggleApiInstructions() {
            const content = document.getElementById("api-instructions-content");
            const icon = document.querySelector(".toggle-icon");
            
            if (content && icon) {
                if (content.style.display === "none") {
                    content.style.display = "block";
                    icon.textContent = "▲";
                } else {
                    content.style.display = "none";
                    icon.textContent = "▼";
                }
            }
        }
        
        function refreshModelsList() {
            const apiKey = document.getElementById("chatgpt_api_key");
            
            if (!apiKey || !apiKey.value) {
                alert("Vă rugăm să introduceți mai întâi cheia API OpenAI.");
                return;
            }
            
            const refreshBtn = document.querySelector("button[onclick=\\'refreshModelsList()\\']");
            if (!refreshBtn) return;
            
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
                    api_key: apiKey.value,
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
        
        // JavaScript pentru metabox-ul de editare articol
        jQuery(document).ready(function($) {
            console.log("🚀 AUTO AI NEWS POSTER - JavaScript loaded");
            console.log("🔍 Current page URL:", window.location.href);
            console.log("🔍 Looking for generate button...");
            
            // Verificăm toate elementele relevante
            console.log("📋 Available elements:");
            console.log("   - get-article-button:", $("#get-article-button").length);
            console.log("   - additional-instructions:", $("#additional-instructions").length);
            console.log("   - custom-source-url:", $("#custom-source-url").length);
            console.log("   - post_ID:", $("#post_ID").length);
            
            const generateBtn = $("#get-article-button");
            if (generateBtn.length) {
                console.log("✅ Generate button found:", generateBtn);
                console.log("   - Button HTML:", generateBtn[0].outerHTML);
            } else {
                console.log("❌ Generate button NOT found!");
                console.log("🔍 Searching for any button with \\'generate\\' in class or id...");
                $("button, input[type=button]").each(function() {
                    const elem = $(this);
                    if (elem.attr("id") && elem.attr("id").toLowerCase().includes("generate")) {
                        console.log("Found button with generate in ID:", elem[0].outerHTML);
                    }
                    if (elem.attr("class") && elem.attr("class").toLowerCase().includes("generate")) {
                        console.log("Found button with generate in class:", elem[0].outerHTML);
                    }
                });
            }
            
            // Handler pentru butonul de generare articol
            $("#get-article-button").on("click", function() {
                console.log("🎯 GENERATE ARTICLE BUTTON CLICKED!");
                
                const additionalInstructions = $("#additional-instructions").val();
                const customSourceUrl = $("#custom-source-url").val();
                const postID = $("#post_ID").val();
                const button = $(this);

                console.log("📋 COLLECTED DATA:");
                console.log("   - Post ID:", postID);
                console.log("   - Additional Instructions:", additionalInstructions);
                console.log("   - Custom Source URL:", customSourceUrl);
                console.log("   - Button element:", button);

                // Verificări de validare
                if (!postID) {
                    console.error("❌ POST ID is missing!");
                    alert("Eroare: ID-ul postării lipsește!");
                    return;
                }

                if (!customSourceUrl && !additionalInstructions) {
                    console.warn("⚠️ Both custom URL and instructions are empty");
                }

                // Dezactivăm butonul și adăugăm un loader
                button.prop("disabled", true);
                button.html("⏳ Generare...");
                console.log("🔄 Button disabled, starting AJAX call...");

                const ajaxData = {
                    action: "get_article_from_sources",
                    post_id: postID,
                    instructions: additionalInstructions,
                    custom_source_url: customSourceUrl,
                    additional_instructions: additionalInstructions,
                    security: "' . wp_create_nonce('get_article_from_sources_nonce') . '"
                };
                
                console.log("📤 AJAX DATA TO SEND:", ajaxData);

                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    method: "POST",
                    data: ajaxData,
                    beforeSend: function(xhr) {
                        console.log("📡 AJAX Request starting...");
                        console.log("   - URL:", "' . admin_url('admin-ajax.php') . '");
                        console.log("   - Method: POST");
                    },
                    success: function(response) {
                        console.log("✅ AJAX SUCCESS - Raw response:", response);
                        console.log("📊 Response type:", typeof response);
                        console.log("🔍 Response success property:", response.success);
                        
                        if (response.success) {
                            console.log("🎉 Article generation successful!");
                            console.log("📝 Response data:", response.data);
                            
                            if (response.data && response.data.post_id) {
                                const redirectUrl = "' . admin_url('post.php') . '?post=" + response.data.post_id + "&action=edit";
                                console.log("🔄 Redirecting to:", redirectUrl);
                                window.location.href = redirectUrl;
                            } else {
                                console.error("❌ No post_id in response data");
                                alert("Eroare: ID-ul postării nu a fost returnat");
                            }
                        } else {
                            console.error("❌ AJAX Success but response.success is false");
                            console.error("📋 Error message:", response.data ? response.data.message : "No message");
                            const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscută";
                            alert("A apărut o eroare: " + errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("💥 AJAX ERROR occurred!");
                        console.error("   - Status:", status);
                        console.error("   - Error:", error);
                        console.error("   - Response Text:", xhr.responseText);
                        console.error("   - Status Code:", xhr.status);
                        console.error("   - Ready State:", xhr.readyState);
                        
                        alert("A apărut o eroare la procesarea cererii. Verifică consola pentru detalii.");
                    },
                    complete: function(xhr, status) {
                        console.log("🏁 AJAX COMPLETE - Status:", status);
                        // Reactivăm butonul și eliminăm loader-ul
                        button.prop("disabled", false);
                        button.html("<span>✨</span> Generează articol");
                        console.log("🔄 Button re-enabled");
                    }
                });
            });
            
            // Handler pentru butonul de generare imagine AI
            $("#generate-image-button").on("click", function() {
                console.log("🎯 GENERATE IMAGE BUTTON CLICKED!");
                
                const postID = $("#post_ID").val();
                const button = $(this);
                const feedbackText = $("#feedback-text").val();

                console.log("📋 COLLECTED DATA for image generation:");
                console.log("   - Post ID:", postID);
                console.log("   - Feedback Text:", feedbackText);

                if (!postID) {
                    console.error("❌ POST ID is missing for image generation!");
                    alert("Eroare: ID-ul postării lipsește pentru generarea imaginii!");
                    return;
                }
                
                button.prop("disabled", true);
                button.html("⏳ Generare imagine...");
                console.log("🔄 Image generation button disabled, starting AJAX call...");

                const ajaxData = {
                    action: "generate_image_for_article",
                    post_id: postID,
                    feedback: feedbackText,
                    security: autoAiNewsPosterAjax.generate_image_nonce
                };

                console.log("📤 AJAX DATA TO SEND for image generation:", ajaxData);

                $.ajax({
                    url: autoAiNewsPosterAjax.ajax_url,
                    method: "POST",
                    data: ajaxData,
                    beforeSend: function(xhr) {
                        console.log("📡 AJAX Image Request starting...");
                    },
                    success: function(response) {
                        console.log("✅ AJAX SUCCESS - Raw image response:", response);
                        
                        if (response.success) {
                            console.log("🎉 Image generation successful!");
                            location.reload();
                        } else {
                            console.error("❌ AJAX Success but image response.success is false");
                            const errorMsg = response.data && response.data.message ? response.data.message : "Eroare necunoscută la generarea imaginii.";
                            console.error("📋 Image Error message:", errorMsg);
                            alert("A apărut o eroare: " + errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("💥 AJAX IMAGE ERROR occurred!");
                        console.error("   - Status:", status);
                        console.error("   - Error:", error);
                        console.error("   - Response Text:", xhr.responseText);
                        alert("A apărut o eroare la generarea imaginii. Verifică consola pentru detalii.");
                    },
                    complete: function(xhr, status) {
                        console.log("🏁 AJAX IMAGE COMPLETE - Status:", status);
                        button.prop("disabled", false);
                        button.html("<span>🎨</span> Generează imagine AI");
                        console.log("🔄 Image generation button re-enabled");
                    }
                });
            });
        });
        </script>';
    }
}

// CSS-ul este încorporat inline pentru a evita problemele MIME type
// Nu mai încărcăm fișiere externe
