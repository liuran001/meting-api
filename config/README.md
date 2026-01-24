# 配置文件说明

## 文件结构

```
config/
├── default.php           # 默认配置（不要修改）
├── loader.php            # 配置加载器（不要修改）
├── local.example.php     # 配置示例文件
└── local.php             # 本地配置（需自行创建，git 会忽略）
```

## 使用方法

### 1. 创建本地配置

```bash
# 复制示例文件
cp config/local.example.php config/local.php

# 编辑本地配置
vim config/local.php
```

### 2. 配置项说明

#### API 基础配置
- `api_uri`: API URI，留空则自动检测

#### 功能开关
- `tlyric`: 中文歌词翻译（默认 true）
- `cache`: 文件缓存（默认 false）
- `cache_time`: 缓存时间（秒，默认 86400）
- `apcu_cache`: APCu 内存缓存（默认 false，需安装扩展）

#### 安全配置
- `auth`: API 认证开关（默认 false）
- `auth_secret`: API 认证密钥
- `query_secret`: Query 查询接口密钥
- `query_forbidden`: 禁止的 SQL 关键词

#### 数据库配置
- `db_path`: 数据库路径
- `db_enable_detail`: 是否启用详情记录
- `db_enable_stats`: 是否启用统计表

#### 音乐平台 Cookie
- `netease_cookie`: 网易云音乐 Cookie（用于获取 VIP 歌曲）
- `qm_cookie_file`: QQ 音乐 Cookie 文件路径

#### 高级配置
- `default_br`: 默认音质（比特率）
- `cors_origin`: 跨域配置
- `response_format`: 响应格式

## 配置优先级

`local.php` > `default.php`

本地配置会覆盖默认配置中的同名项。

## 注意事项

1. **不要修改** `default.php` 和 `loader.php`
2. **不要提交** `local.php` 到 git（已在 .gitignore 中配置）
3. 敏感信息（密钥、Cookie）应放在 `local.php` 中
4. 网易云 Cookie 获取方式：
   - 浏览器登录 https://music.163.com/
   - 打开开发者工具（F12）
   - 在 Network 标签中找到请求
   - 复制请求头中的 Cookie 字段
