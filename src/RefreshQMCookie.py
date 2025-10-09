import re
import time
import json
import requests
from pathlib import Path
import logging
import os
import sys

isPackaged: bool = not sys.argv[0].endswith('.py')

# 配置日志
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler()
    ]
)

# 配置文件路径
if isPackaged:
    exe_path = Path(sys.argv[0]).resolve()
    base_path = exe_path.parent
    os.chdir(base_path)
    BASE_DIR = base_path
else:
    BASE_DIR = Path(__file__).resolve().parent

INDEX_PHP = BASE_DIR.parent / "index.php"
COOKIE_PHP = BASE_DIR / "QMCookie.php"

def extract_index_cookie():
    try:
        with open(INDEX_PHP, "r", encoding="utf-8") as f:
            content = f.read()
        logging.debug(f"读取的PHP文件内容片段: {content[:500]}...")
        # 更可靠的正则表达式匹配 - 直接匹配 Cookie 字符串
        pattern = r"else if \(server == 'tencent' && \$tencent_cookie == 'local'\)\s*\{\s*\$api->cookie\('([^']+)'\)"
        match = re.search(pattern, content)
        if match:
            logging.info("成功匹配到内嵌 Cookie")
            return match.group(1)
        # 备用匹配模式 - 匹配整个 Cookie 字符串
        pattern_alt = r"\$api->cookie\('(pgv_pvid=[^']+)'\)"
        match_alt = re.search(pattern_alt, content)
        if match_alt:
            logging.info("使用备用模式匹配到内嵌 Cookie")
            return match_alt.group(1)
        logging.warning("无法匹配内嵌 Cookie 字符串")
        return None
    except Exception as e:
        logging.error(f"提取主文件 Cookie 失败: {str(e)}")
        return None

def parse_cookie(cookie_str):
    """解析 Cookie 字符串为字典"""
    if not cookie_str:
        return {}
    try:
        cookie_dict = {}
        for item in cookie_str.split("; "):
            if "=" in item:
                key, value = item.split("=", 1)
                cookie_dict[key.strip()] = value.strip()
        return cookie_dict
    except Exception as e:
        logging.error(f"解析 Cookie 失败: {str(e)}")
        return {}

def load_stored_cookie():
    """读取存储的 Cookie 数据"""
    try:
        if not COOKIE_PHP.exists():
            logging.warning(f"存储文件不存在: {COOKIE_PHP}")
            return None
        with open(COOKIE_PHP, "r", encoding="utf-8") as f:
            content = f.read()
        # 匹配存储的 Cookie 值
        pattern = r"\$QMCookie\s*=\s*'([^']*)'"
        match = re.search(pattern, content)
        if match:
            cookie_value = match.group(1)
            logging.info(f"读取到存储的 Cookie: {cookie_value[:30]}...")  # 只显示前30个字符
            return cookie_value
        logging.warning("存储文件中未找到 Cookie 值")
        return None
    except Exception as e:
        logging.error(f"读取存储的 Cookie 失败: {str(e)}")
        return None

def is_cookie_valid(cookie_str):
    """检查 Cookie 是否有效"""
    # 检查特殊值
    if not cookie_str or cookie_str.lower() in ("", "null", "undefined", "local"):
        logging.info(f"无效 Cookie: {cookie_str}")
        return False
    try:
        cookie_dict = parse_cookie(cookie_str)
        create_time = int(cookie_dict.get("psrf_musickey_createtime", "0") or 0)
        current_time = int(time.time())
        # 记录调试信息
        logging.debug(f"Cookie 创建时间: {create_time}")
        logging.debug(f"当前时间: {current_time}")
        logging.debug(f"过期时间: {create_time + 86400}")
        # 检查是否在有效期内（24小时）
        if create_time <= 0:
            logging.info("无效 Cookie: 缺少时间戳")
            return False
        if current_time >= (create_time + 86400):
            logging.info(f"Cookie 已过期: {current_time - (create_time + 86400)}秒")
            return False
        logging.info("Cookie 有效")
        return True
    except Exception as e:
        logging.error(f"验证 Cookie 有效性失败: {str(e)}")
        return False

def is_cookie_near_expiry(cookie_str):
    """检查 Cookie 是否临近过期（超过20小时但未满24小时）"""
    if not cookie_str:
        return False
    try:
        cookie_dict = parse_cookie(cookie_str)
        create_time = int(cookie_dict.get("psrf_musickey_createtime", "0") or 0)
        current_time = int(time.time())
        # 检查是否在临近过期状态
        return (create_time > 0 and
                current_time > (create_time + 72000) and  # 20小时
                current_time < (create_time + 86400))     # 24小时
    except Exception as e:
        logging.error(f"检查 Cookie 过期状态失败: {str(e)}")
        return False

def renew_cookie(cookie_str):
    """执行 Cookie 续签"""
    if not cookie_str:
        logging.warning("续签失败: 无有效 Cookie")
        return None
    try:
        cookie_dict = parse_cookie(cookie_str)
        url = "https://u6.y.qq.com/cgi-bin/musicu.fcg?format=json&inCharset=utf8&outCharset=utf8"
        headers = {
            "Referer": "https://y.qq.com/",
            "Origin": "https://y.qq.com",
            "Content-Type": "application/json",
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36"
        }
        # 构建请求体
        payload = {
            "code": 0,
            "req1": {
                "code": 0,
                "module": "QQConnectLogin.LoginServer",
                "method": "QQLogin",
                "param": {
                    "onlyNeedAccessToken": 0,
                    "forceRefreshToken": 0,
                    "psrf_qqopenid": cookie_dict.get("psrf_qqopenid", ""),
                    "refresh_token": cookie_dict.get("psrf_qqrefresh_token", ""),
                    "access_token": cookie_dict.get("psrf_qqaccess_token", ""),
                    "expired_at": cookie_dict.get("psrf_access_token_expiresAt", ""),
                    "musicid": int(cookie_dict.get("uin", "").strip('"')),
                    "musickey": cookie_dict.get("qqmusic_key", ""),
                    "musickeyCreateTime": int(cookie_dict.get("psrf_musickey_createtime", "0") or 0),
                    "unionid": cookie_dict.get("psrf_qqunionid", ""),
                    "str_musicid": cookie_dict.get("uin", ""),
                    "encryptUin": cookie_dict.get("euin", "")
                }
            }
        }
        logging.info(f"发送续签请求到: {url}")
        logging.debug(f"请求载荷: {json.dumps(payload, indent=2)}")
        response = requests.post(
            url,
            json=payload,
            headers=headers,
            cookies=cookie_dict,
            timeout=15
        )
        if response.status_code != 200:
            logging.error(f"续签请求失败: HTTP {response.status_code}")
            return None
        data = response.json()
        logging.debug(f"续签响应: {json.dumps(data, indent=2)}")
        if data.get("code") == 0 and data["req1"].get("code") == 0:
            logging.info("续签请求成功")
            return data["req1"]["data"]
        else:
            logging.error(f"续签返回错误: {json.dumps(data, ensure_ascii=False)}")
            return None
    except Exception as e:
        logging.error(f"续签过程中出错: {str(e)}")
        return None

def update_cookie_file(new_cookie_str):
    """更新存储的 Cookie 文件"""
    if not new_cookie_str:
        logging.warning("跳过更新: 空 Cookie")
        return
    try:
        # 确保目录存在
        COOKIE_PHP.parent.mkdir(parents=True, exist_ok=True)
        # 转义单引号防止破坏 PHP 结构
        escaped_cookie = new_cookie_str.replace("'", "\\'")
        php_content = f"""<?php if (!defined('METING_API')) die('Access Denied');
$QMCookie = '{escaped_cookie}';
?>"""
        with open(COOKIE_PHP, "w", encoding="utf-8") as f:
            f.write(php_content)
        logging.info("存储的 Cookie 文件已更新")
    except Exception as e:
        logging.error(f"更新 Cookie 文件失败: {str(e)}")

def update_cookie_with_data(original_cookie_str, new_data):
    """用续签数据更新原始 Cookie"""
    if not original_cookie_str or not new_data:
        return original_cookie_str
    try:
        cookie_dict = parse_cookie(original_cookie_str)
        # 更新关键字段
        update_fields = {
            "psrf_qqrefresh_token": new_data.get("refresh_token"),
            "psrf_qqaccess_token": new_data.get("access_token"),
            "qqmusic_key": new_data.get("musickey"),
            "qm_keyst": new_data.get("musickey"),
            "psrf_musickey_createtime": str(new_data.get("musickeyCreateTime", "")),
            "psrf_qqunionid": new_data.get("unionid"),
            "login_type": new_data.get("login_type")
        }
        # 只更新非空值
        for key, value in update_fields.items():
            if value and value != "0" and value != 0:
                cookie_dict[key] = value
        # 重新生成 Cookie 字符串
        return "; ".join(f"{k}={v}" for k, v in cookie_dict.items())
    except Exception as e:
        logging.error(f"更新 Cookie 数据失败: {str(e)}")
        return original_cookie_str

def main():
    logging.info(f" ")
    logging.info(f" ")
    logging.info(f" ")
    logging.info(f"---------------")
    logging.info(f"QQMusic Cookie 续签服务启动")
    logging.info(f"")
    logging.info(f"版本: 1.0.0")
    logging.info(f"Made by NanoRocky")
    logging.info(f"https://github.com/NanoRocky/QMusicCookieRefresh")
    logging.info(f"---------------")
    logging.info(f"INDEX_PHP 路径: {INDEX_PHP}")
    logging.info(f"COOKIE_PHP 路径: {COOKIE_PHP}")
    logging.info(f"当前工作目录: {BASE_DIR}")
    logging.info(f"---------------")
    while True:
        try:
            # 1. 读取存储的 Cookie
            stored_cookie = load_stored_cookie()
            # 2. 检查存储的 Cookie 是否有效
            if stored_cookie and is_cookie_valid(stored_cookie):
                logging.info("存储的 Cookie 有效")
            else:
                logging.warning("存储的 Cookie 无效或不存在")
                # 3. 当存储的 Cookie 无效时，尝试从主 PHP 读取内嵌 Cookie
                index_cookie = extract_index_cookie()
                if index_cookie:
                    logging.info(f"提取到内嵌 Cookie: {index_cookie[:50]}...")
                    if is_cookie_valid(index_cookie):
                        logging.info("内嵌 Cookie 有效，更新存储")
                        update_cookie_file(index_cookie)
                        stored_cookie = index_cookie
                    else:
                        logging.warning("内嵌 Cookie 无效")
                else:
                    logging.error("无法提取内嵌 Cookie")
                if not stored_cookie or not is_cookie_valid(stored_cookie):
                    logging.error("无有效 Cookie，等待1小时")
                    time.sleep(3600)
                    continue
            # 4. 检查主文件 Cookie 是否更新
            index_cookie = extract_index_cookie()
            if index_cookie and index_cookie != stored_cookie and is_cookie_valid(index_cookie):
                index_dict = parse_cookie(index_cookie)
                stored_dict = parse_cookie(stored_cookie)
                # 比较创建时间戳
                index_time = int(index_dict.get("psrf_musickey_createtime", "0") or 0)
                stored_time = int(stored_dict.get("psrf_musickey_createtime", "0") or 0)
                if index_time > stored_time:
                    update_cookie_file(index_cookie)
                    logging.info("检测到更新的主文件 Cookie，覆盖存储")
                    stored_cookie = index_cookie
            # 5. 检查是否需要续签
            if is_cookie_near_expiry(stored_cookie):
                logging.info("Cookie 临近过期，执行续签...")
                new_data = renew_cookie(stored_cookie)
                if new_data:
                    new_cookie = update_cookie_with_data(stored_cookie, new_data)
                    update_cookie_file(new_cookie)
                    logging.info("续签成功并更新存储")
                    # 计算下次执行时间（20小时后）
                    cookie_dict = parse_cookie(new_cookie)
                    create_time = int(cookie_dict.get("psrf_musickey_createtime", "0") or 0)
                    next_run = max(3600, (create_time + 72000) - time.time())
                    logging.info(f"下次续签在 {next_run/3600:.1f} 小时后")
                    time.sleep(next_run)
                else:
                    logging.warning("续签失败，1小时后重试")
                    time.sleep(3600)
            else:
                # 6. 正常状态，计算等待时间
                cookie_dict = parse_cookie(stored_cookie)
                create_time = int(cookie_dict.get("psrf_musickey_createtime", "0") or 0)
                if create_time > 0:
                    # 计算距离20小时的时间
                    wait_time = max(300, (create_time + 72000) - time.time())
                    logging.info(f"Cookie 状态正常，{wait_time/3600:.1f} 小时后检查")
                    time.sleep(wait_time)
                else:
                    logging.warning("缺少时间戳，1小时后检查")
                    time.sleep(3600)
        except Exception as e:
            logging.critical(f"主循环发生错误: {str(e)}", exc_info=True)
            logging.info("10分钟后重试")
            time.sleep(600)

if __name__ == "__main__":
    main()