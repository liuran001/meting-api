<?php
/**
 * Handsome 主题兼容接口
 * 
 * Handsome 主题使用的是特殊的 Meting API 接口格式
 * 主要区别：
 * 1. song 和 playlist 返回 application/javascript 而不是 application/json
 * 2. 返回数据中使用 'cover' 而不是 'pic'
 * 
 * 使用方法：
 * 在 Handsome 主题的开发者高级设置中填写：
 * {"music_api":"https://your-domain.com/meting/handsome.php?server=:server&type=:type&id=:id"}
 */

// 检查是否已经通过 index.php 路由进来
if (!defined('API_URI')) {
	// 自动开启 handsome 兼容模式
	$_GET['handsome'] = 'true';
	define('HANDSOME_MODE', true);
    
	// 包含主入口文件
	require __DIR__ . '/index.php';
} else {
	// 已经通过 index.php 路由，不需要再次包含
	// 这种情况不应该发生，但作为保护
	die('This file should not be accessed directly when routed through index.php');
}
