CREATE TABLE IF NOT EXISTS __PREFIX__admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    reset_token VARCHAR(255) DEFAULT '--',
    reset_token_expires_at VARCHAR(255) DEFAULT '--',
    reset_token_used_at VARCHAR(255) DEFAULT '--',
    a_status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(255) DEFAULT '--',
    favicon VARCHAR(1000) DEFAULT '--',
    logo VARCHAR(1000) DEFAULT '--',
    default_timezone VARCHAR(100) DEFAULT 'America/New_York',
    default_currency VARCHAR(50) DEFAULT 'USD',
    currency_symbol VARCHAR(10) DEFAULT '$',
    api_key VARCHAR(100) DEFAULT '--',
    webhook VARCHAR(200) DEFAULT '--',
    global_text_color VARCHAR(20) DEFAULT '#3bb77e',
    primary_button_color VARCHAR(20) DEFAULT '#3bb77e',
    button_text_color VARCHAR(20) DEFAULT '#FFFFFF',
    button_hover_color VARCHAR(20) DEFAULT '#3bb77e',
    button_hover_text_color VARCHAR(20) DEFAULT '#FFFFFF',
    navigation_background VARCHAR(20) DEFAULT '#3bb77e',
    navigation_text_color VARCHAR(20) DEFAULT '#FFFFFF',
    active_tab_color VARCHAR(20) DEFAULT '#3bb77e',
    active_tab_text_color VARCHAR(20) DEFAULT '#FFFFFF',
    gateway_theme VARCHAR(255) DEFAULT 'vercel',
    invoice_theme VARCHAR(255) DEFAULT 'vercel',
    street_address VARCHAR(255) DEFAULT '--',
    city_town VARCHAR(255) DEFAULT '--',
    postal_zip_code VARCHAR(50) DEFAULT '--',
    country VARCHAR(100) DEFAULT '--',
    support_phone_number VARCHAR(50) DEFAULT '--',
    support_email_address VARCHAR(255) DEFAULT '--',
    support_website VARCHAR(255) DEFAULT '--',
    facebook_page VARCHAR(255) DEFAULT '--',
    facebook_messenger VARCHAR(255) DEFAULT '--',
    whatsapp_number VARCHAR(50) DEFAULT '--',
    telegram VARCHAR(255) DEFAULT '--',
    youtube_channel VARCHAR(255) DEFAULT '--',
    footer_support_url VARCHAR(255) DEFAULT '--',
    footer_faq_url VARCHAR(255) DEFAULT '--',
    footer_information_url VARCHAR(255) DEFAULT '--',
    footer_terms_url VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__sms_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_type VARCHAR(255) DEFAULT '--',
    sim VARCHAR(255) DEFAULT 'sim1',
    payment_method VARCHAR(255) DEFAULT '--',
    mobile_number VARCHAR(255) DEFAULT '--',
    transaction_id VARCHAR(255) DEFAULT '--',
    amount VARCHAR(255) DEFAULT '--',
    balance VARCHAR(255) DEFAULT '--',
    message VARCHAR(755) DEFAULT '--',
    status VARCHAR(755) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--',
    INDEX idx_transaction_id (transaction_id(191)),
    INDEX idx_status (status(191)),
    INDEX idx_payment_method (payment_method(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__transaction (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pp_id VARCHAR(755) DEFAULT '--',
    c_id VARCHAR(255) DEFAULT '--',
    c_name VARCHAR(255) DEFAULT '--',
    c_email_mobile VARCHAR(255) DEFAULT '--',
    payment_method_id VARCHAR(255) DEFAULT '--',
    payment_method VARCHAR(255) DEFAULT '--',
    payment_verify_way VARCHAR(255) DEFAULT '--',
    payment_sender_number VARCHAR(255) DEFAULT '--',
    payment_verify_id VARCHAR(255) DEFAULT '--',
    transaction_amount VARCHAR(255) DEFAULT '--',
    transaction_fee VARCHAR(255) DEFAULT '--',
    transaction_refund_amount VARCHAR(755) DEFAULT '--',
    transaction_refund_reason VARCHAR(755) DEFAULT '--',
    transaction_currency VARCHAR(755) DEFAULT '--',
    transaction_redirect_url VARCHAR(755) DEFAULT '--',
    transaction_return_type VARCHAR(155) DEFAULT '--',
    transaction_cancel_url VARCHAR(755) DEFAULT '--',
    transaction_webhook_url VARCHAR(755) DEFAULT '--',
    transaction_metadata VARCHAR(755) DEFAULT '--',
    transaction_status VARCHAR(755) DEFAULT '--',
    transaction_product_name VARCHAR(255) DEFAULT '--',
    transaction_product_description VARCHAR(755) DEFAULT '--',
    transaction_product_meta VARCHAR(1755) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--',
    INDEX idx_transaction_pp_id (pp_id(191)),
    INDEX idx_transaction_verify_id (payment_verify_id(191)),
    INDEX idx_transaction_status (transaction_status(191)),
    INDEX idx_transaction_created_at (created_at(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__customer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    c_id VARCHAR(255) DEFAULT '--',
    c_name VARCHAR(255) DEFAULT '--',
    c_email_mobile VARCHAR(255) DEFAULT '--',
    c_status VARCHAR(755) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__invoice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    i_id VARCHAR(755) DEFAULT '--',
    c_id VARCHAR(255) DEFAULT '--',
    c_name VARCHAR(255) DEFAULT '--',
    c_email_mobile VARCHAR(255) DEFAULT '--',
    i_currency VARCHAR(255) DEFAULT '--',
    i_due_date VARCHAR(255) DEFAULT '--',
    i_status VARCHAR(255) DEFAULT '--',
    i_note VARCHAR(755) DEFAULT '--',
    i_amount_shipping VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    i_id VARCHAR(755) DEFAULT '--',
    description VARCHAR(255) DEFAULT '--',
    quantity VARCHAR(255) DEFAULT '0',
    amount VARCHAR(255) DEFAULT '0',
    discount VARCHAR(255) DEFAULT '0',
    vat VARCHAR(255) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__payment_link (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pl_id VARCHAR(755) DEFAULT '--',
    pl_name VARCHAR(255) DEFAULT '--',
    pl_quantity VARCHAR(255) DEFAULT '--',
    pl_description VARCHAR(255) DEFAULT '--',
    pl_currency VARCHAR(255) DEFAULT '--',
    pl_amount VARCHAR(255) DEFAULT '--',
    pl_expiry_date VARCHAR(255) DEFAULT '--',
    pl_status VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__payment_link_input (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pl_id VARCHAR(755) DEFAULT '--',
    pl_form_type VARCHAR(255) DEFAULT '--',
    pl_field_name VARCHAR(255) DEFAULT '--',
    pl_is_require VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    d_id VARCHAR(755) DEFAULT '--',
    d_model VARCHAR(255) DEFAULT '--',
    d_brand VARCHAR(255) DEFAULT '--',
    d_version VARCHAR(255) DEFAULT '--',
    d_api_level VARCHAR(255) DEFAULT '--',
    d_status VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__browser_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    a_id VARCHAR(755) DEFAULT '--',
    cookie VARCHAR(755) DEFAULT '--',
    browser VARCHAR(755) DEFAULT '--',
    device VARCHAR(755) DEFAULT '--',
    ip VARCHAR(255) DEFAULT '--',
    status VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__currency (
    id INT AUTO_INCREMENT PRIMARY KEY,
    currency_code VARCHAR(255) DEFAULT '--',
    currency_name VARCHAR(255) DEFAULT '--',
    currency_symbol VARCHAR(255) DEFAULT '--',
    currency_rate VARCHAR(255) DEFAULT '0',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__timezone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_code VARCHAR(255) DEFAULT '--',
    timezone VARCHAR(255) DEFAULT '--',
    gmt_offset VARCHAR(255) DEFAULT '--',
    dst_offset VARCHAR(255) DEFAULT '--',
    raw_offset VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT '--',
    content VARCHAR(1255) DEFAULT '--',
    status VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS __PREFIX__plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin_name VARCHAR(255) DEFAULT '--',
    plugin_slug VARCHAR(1255) DEFAULT '--',
    plugin_dir VARCHAR(255) DEFAULT '--',
    plugin_array VARCHAR(6055) DEFAULT '--',
    status VARCHAR(255) DEFAULT '--',
    created_at VARCHAR(255) DEFAULT '--'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;