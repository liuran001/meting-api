<?php
/**
 * Meting-API 默认配置
 * 
 * 所有配置项集中管理
 * 可以通过创建 config/local.php 进行个性化配置覆盖
 */

return [
    // ==================== API 基础配置 ====================
    
    /**
     * API URI 自动检测
     * 留空则自动根据请求生成，也可以手动指定，例如：'https://api.example.com'
     */
    'api_uri' => '',
    
    // ==================== 功能开关 ====================
    
    /**
     * 中文歌词翻译
     * true: 启用翻译歌词
     * false: 禁用
     */
    'tlyric' => true,
    
    /**
     * 歌单文件缓存
     * true: 启用文件缓存
     * false: 禁用
     */
    'cache' => false,
    
    /**
     * 文件缓存时间（秒）
     * 默认 86400 秒（24小时）
     */
    'cache_time' => 86400,
    
    /**
     * 缓存目录
     * 相对于项目根目录
     */
    'cache_dir' => __DIR__ . '/cache',
    
    /**
     * APCu 内存缓存（需要安装 APCu 扩展）
     * true: 启用 APCu 缓存
     * false: 禁用
     */
    'apcu_cache' => false,
    
    // ==================== 安全配置 ====================
    
    /**
     * API 认证开关
     * true: 启用签名认证
     * false: 禁用（公开API）
     */
    'auth' => false,
    
    /**
     * API 认证密钥
     * 用于 HMAC-SHA1 签名验证，请务必修改默认值！
     */
    'auth_secret' => 'meting-secret',
    
    /**
     * Query 查询接口密钥
     * 用于保护 query.php 的敏感操作（删除/更新等）
     */
    'query_secret' => 'meting-query-secret',
    
    /**
     * Query 查询接口禁止的 SQL 关键词
     * 防止恶意 SQL 注入
     */
    'query_forbidden' => [
        'delete', 'update', 'insert', 'create', 'drop', 
        'alter', 'begin', 'rollback', 'commit', 'vacuum', 
        'attach', 'detach'
    ],
    
    // ==================== 数据库配置 ====================
    
    /**
     * PV 统计数据库路径
     */
    'db_path' => __DIR__ . '/db/pv.db',
    
    /**
     * 是否启用详情记录（DETAIL表）
     * true: 记录每次请求详情
     * false: 仅统计计数
     */
    'db_enable_detail' => true,
    
    /**
     * 是否启用统计表（STATS表）
     * true: 启用聚合统计表（推荐，性能更好）
     * false: 仅使用详情表
     */
    'db_enable_stats' => true,
    
    // ==================== 音乐平台 Cookie 配置 ====================
    
    /**
     * 网易云音乐 Cookie
     * 用于获取网易云音乐资源（VIP 歌曲等）
     * 留空则使用默认 Cookie
     */
    'netease_cookie' => 'appver=8.2.30; os=iPhone OS; osver=15.0; EVNSM=1.0.0; buildver=2206; channel=distribution; machineid=iPhone13.3',
    
    /**
     * QQ 音乐 Cookie 文件路径
     * 用于获取 QQ 音乐资源
     */
    'qm_cookie_file' => __DIR__ . '/src/QMCookie.php',
    
    // ==================== 高级配置 ====================
    
    /**
     * 默认音质（比特率）
     * 单位：kbps，例如：320, 1411（无损）
     */
    'default_br' => 2147483,
    
    /**
     * 跨域配置
     * 设置允许的来源，'*' 表示允许所有来源
     */
    'cors_origin' => '*',
    
    /**
     * 响应格式
     * 'json': 返回 JSON 格式
     * 'jsonp': 支持 JSONP 回调
     */
    'response_format' => 'json',
];
