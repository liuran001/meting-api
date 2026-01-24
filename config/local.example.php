<?php
/**
 * 本地配置文件示例
 * 
 * 复制此文件为 local.php 并根据需要修改配置
 * local.php 不会被 git 跟踪，适合存放个性化配置
 */

return [
    // ==================== API 基础配置 ====================
    
    // 'api_uri' => 'https://music-api.example.com',
    
    // ==================== 功能开关 ====================
    
    // 'tlyric' => true,
    // 'cache' => true,
    // 'cache_time' => 3600,  // 1小时
    // 'apcu_cache' => true,
    
    // ==================== 安全配置 ====================
    
    // 'auth' => true,
    // 'auth_secret' => 'your-custom-secret-key-here',
    // 'query_secret' => 'your-query-secret-here',
    
    // ==================== 数据库配置 ====================
    
    // 'db_path' => '/var/www/data/pv.db',
    // 'db_enable_detail' => false,  // 关闭详情记录以节省空间
    
    // ==================== 音乐平台 Cookie 配置 ====================
    
    // 网易云音乐 Cookie（获取 VIP 歌曲）
    // 获取方式：浏览器登录 music.163.com，F12 开发者工具中复制 Cookie
    // 'netease_cookie' => 'MUSIC_U=xxx; __csrf=xxx; ...',
    
    // QQ 音乐 Cookie 文件
    // 'qm_cookie_file' => '/path/to/custom/QMCookie.php',
];
