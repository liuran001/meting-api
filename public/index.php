<!DOCTYPE html>
<html lang="zh-cn">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="favicon.png">
    <title>Meting-API</title>
    <!-- APlayer -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aplayer/1.10.1/APlayer.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aplayer/1.10.1/APlayer.min.js"></script>
    <!-- fetch-jsonp for QQ 音乐跨域 JSONP -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fetch-jsonp/1.1.3/fetch-jsonp.min.js"></script>
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400;500;700&display=swap" />
    <!-- MD3-like minimal theme -->
    <style>
        :root {
            --md-primary: #6750A4;
            --md-on-primary: #FFFFFF;
            --md-primary-container: #EADDFF;
            --md-on-primary-container: #21005D;
            --md-surface: #FFFBFE;
            --md-on-surface: #1C1B1F;
            --md-surface-variant: #E7E0EC;
            --md-outline: #79747E;
            --md-error: #B3261E;
        }
        /* Dark scheme overrides */
        :root[data-theme="dark"] {
            --md-primary: #D0BCFF;
            --md-on-primary: #371E73;
            --md-primary-container: #4F378B;
            --md-on-primary-container: #EADDFF;
            --md-surface: #1C1B1F;
            --md-on-surface: #E6E1E5;
            --md-surface-variant: #49454F;
            --md-outline: #938F99;
            --md-error: #F2B8B5;
        }

        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "PingFang SC", "Microsoft Yahei", sans-serif;
            background: var(--md-surface);
            color: var(--md-on-surface);
        }
        .app-bar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--md-primary);
            color: var(--md-on-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .app-bar .title {
            max-width: 1120px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            font-size: 20px;
            font-weight: 600;
        }
        .app-bar .spacer { flex: 1; }
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .stats-badge:hover {
            background: rgba(255,255,255,0.25);
        }
        .stats-badge:active {
            background: rgba(255,255,255,0.3);
        }
        .stats-badge .number {
            font-weight: 700;
            font-size: 16px;
        }
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.3);
            background: transparent;
            color: inherit;
            cursor: pointer;
        }
        .container {
            max-width: 1120px;
            margin: 24px auto;
            padding: 0 16px;
        }
        .section {
            margin-bottom: 24px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid var(--md-surface-variant);
            overflow: hidden;
        }
        :root[data-theme="dark"] .card {
            background: #2B2930;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .card-header {
            padding: 16px 20px;
            background: var(--md-primary-container);
            color: var(--md-on-primary-container);
            font-weight: 600;
        }
        .card-content { 
            padding: 18px 20px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        @media (min-width: 900px) {
            .grid { grid-template-columns: 1.2fr 1fr; }
        }
        .aplayer-wrap { display: flex; justify-content: center; }
        #aplayer { width: 100%; max-width: 900px; }
        /* APlayer 深色模式适配 */
        :root[data-theme="dark"] .aplayer {
            background: #2B2930;
        }
        :root[data-theme="dark"] .aplayer .aplayer-body,
        :root[data-theme="dark"] .aplayer .aplayer-list {
            background: #2B2930;
        }
        :root[data-theme="dark"] .aplayer .aplayer-info {
            border-top-color: #49454F;
        }
        :root[data-theme="dark"] .aplayer .aplayer-info .aplayer-music .aplayer-title {
            color: #E6E1E5;
        }
        :root[data-theme="dark"] .aplayer .aplayer-info .aplayer-music .aplayer-author {
            color: #CAC4D0;
        }
        :root[data-theme="dark"] .aplayer .aplayer-list ol li {
            color: #E6E1E5;
            border-top-color: #49454F;
        }
        :root[data-theme="dark"] .aplayer .aplayer-list ol li:hover {
            background: #383539;
        }
        :root[data-theme="dark"] .aplayer .aplayer-list ol li.aplayer-list-light {
            background: #4F378B;
        }
        :root[data-theme="dark"] .aplayer .aplayer-list ol li .aplayer-list-author {
            color: #938F99;
        }
        :root[data-theme="dark"] .aplayer .aplayer-lrc {
            text-shadow: -1px -1px 0 #1C1B1F;
        }
        :root[data-theme="dark"] .aplayer .aplayer-lrc:before {
            background: linear-gradient(to bottom, #2B2930 0%, rgba(43, 41, 48, 0) 100%);
        }
        :root[data-theme="dark"] .aplayer .aplayer-lrc:after {
            background: linear-gradient(to bottom, rgba(43, 41, 48, 0) 0%, rgba(43, 41, 48, 0.8) 100%);
        }
        :root[data-theme="dark"] .aplayer .aplayer-lrc p {
            color: #938F99;
        }
        :root[data-theme="dark"] .aplayer .aplayer-lrc p.aplayer-lrc-current {
            color: #E6E1E5;
        }
        .field {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
        }
        .label { font-size: 14px; color: #3D3A40; }
        :root[data-theme="dark"] .label { color: #CAC4D0; }
        .input, select {
            appearance: none;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--md-outline);
            background: #fff;
            color: var(--md-on-surface);
            font-size: 14px;
        }
        :root[data-theme="dark"] .input,
        :root[data-theme="dark"] select {
            background: #1C1B1F;
            color: var(--md-on-surface);
        }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .btn {
            border: none;
            padding: 10px 14px;
            border-radius: 20px;
            background: var(--md-primary);
            color: var(--md-on-primary);
            cursor: pointer;
        }
        .btn.outline {
            background: transparent;
            color: var(--md-primary);
            border: 1px solid var(--md-primary);
        }
        .mono { 
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        .example-list a { 
            display: inline-block; 
            margin: 6px 0; 
            color: var(--md-primary);
            word-break: break-all;
            overflow-wrap: break-word;
        }
        :root[data-theme="dark"] a { color: #D0BCFF; }
        .note { color: var(--md-error); font-weight: 600; }
        .footer { color: #6A6970; margin: 24px 0; }
        :root[data-theme="dark"] .footer { color: #938F99; }
        .copyright {
            text-align: center;
            padding: 20px 16px;
            color: #6A6970;
            font-size: 14px;
            border-top: 1px solid var(--md-surface-variant);
            background: var(--md-surface);
        }
        :root[data-theme="dark"] .copyright {
            color: #938F99;
        }
        .copyright a {
            color: var(--md-primary);
            text-decoration: none;
            font-weight: 500;
        }
        .copyright a:hover {
            text-decoration: underline;
        }
        .copyright .divider {
            margin: 0 8px;
            color: var(--md-outline);
        }
        pre.json { background: #FAFAFA; border: 1px dashed var(--md-outline); padding: 12px; border-radius: 12px; overflow: auto; max-height: 320px; }
        :root[data-theme="dark"] pre.json { background: #1C1B1F; color: #E6E1E5; }
        pre.json.media-preview { padding: 12px; background: transparent; border: none; }
        .result-box {
            background: #FAFAFA;
            border: 1px dashed var(--md-outline);
            padding: 12px;
            border-radius: 12px;
            overflow: auto;
            max-height: 320px;
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        :root[data-theme="dark"] .result-box {
            background: #1C1B1F;
            color: #E6E1E5;
        }
        .result-box.media-content {
            background: transparent;
            border: none;
            padding: 0;
            white-space: normal;
        }
        .handsome-code { 
            color: #1C1B1F;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        :root[data-theme="dark"] .handsome-code { background: #2B2930 !important; color: #E6E1E5; border: 1px solid #49454F; }
    </style>
</head>

<body>
    <header class="app-bar">
        <div class="title">
            <span class="material-symbols-outlined" aria-hidden="true">library_music</span>
            Meting-API
            <span class="spacer"></span>
            <button id="statsRefresh" class="stats-badge" style="border: none; color: inherit;" aria-label="刷新统计数据" title="点击刷新">
                <span class="material-symbols-outlined" style="font-size: 18px;" aria-hidden="true">query_stats</span>
                <span>已解析</span>
                <span class="number" id="count">···</span>
                <span>次</span>
            </button>
            <button id="themeToggle" class="icon-btn" aria-label="切换主题">
                <span id="themeIcon" class="material-symbols-outlined" aria-hidden="true">dark_mode</span>
            </button>
        </div>
    </header>

    <main class="container">

        <!-- 免责声明 -->
        <section class="section card">
            <div class="card-header">⚠️ 免责声明</div>
            <div class="card-content">
                <p style="margin: 0 0 12px 0; line-height: 1.6;">
                    本接口<strong>仅供学习交流使用</strong>，所有音乐数据均来自第三方平台（网易云音乐、QQ音乐等），<strong>不在本服务器存储任何音频文件</strong>。请在获取后 24 小时内删除，切勿用于商业或违法用途。
                </p>
                <p style="margin: 0 0 12px 0; line-height: 1.6;">
                    使用本接口即表示您已知晓并同意：本人<strong>不对因使用本接口产生的任何后果承担责任</strong>，包括但不限于版权纠纷、法律责任等。请遵守当地法律法规及音乐平台的用户协议。
                </p>
                <p style="margin: 0; line-height: 1.6; color: var(--md-outline);">
                    如有异议请联系：<span id="email-placeholder">（加载中...）</span>
                </p>
                <p style="margin: 0; line-height: 1.6; color: var(--md-outline);">
                    疑问及使用咨询：<a href="https://t.me/BDovo" style="color: var(--md-primary); text-decoration: none; font-weight: 500;" target="_blank" rel="noopener noreferrer">Telegram@BDovo</a>
                </p>
            </div>
        </section>

        <section class="section card">
            <div class="card-header">播放器</div>
            <div class="card-content">
                <div class="aplayer-wrap"><div id="aplayer"></div></div>
            </div>
        </section>

        <section class="section grid">
            <!-- 接口测试 -->
            <div class="card">
                <div class="card-header">接口测试</div>
                <div class="card-content">
                    <form id="testForm">
                        <div class="field">
                            <label class="label" for="server">数据源 server</label>
                            <select id="server" name="server">
                                <option value="netease" selected>netease（网易云音乐）</option>
                                <option value="tencent">tencent（QQ音乐）</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="type">类型 type</label>
                            <select id="type" name="type">
                                <option value="song">song（单曲）</option>
                                <option value="playlist" selected>playlist（歌单）</option>
                                <option value="search">search（搜索）</option>
                                <option value="name">name（歌曲名）</option>
                                <option value="artist">artist（歌手）</option>
                                <option value="url">url（链接）</option>
                                <option value="pic">pic（封面）</option>
                                <option value="lrc">lrc（歌词）</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="id">类型ID id（封面/单曲/歌单）</label>
                            <input id="id" class="input" name="id" placeholder="例如 8900628861 或 1969519579；搜索时填 0" value="8900628861" />
                        </div>
                        <div class="field">
                            <label class="label" for="keyword">搜索关键词 keyword（仅 search）</label>
                            <input id="keyword" class="input" name="keyword" placeholder="例如：寄往未来的信" />
                        </div>
                        <div class="field">
                            <label class="label" for="picsize">封面大小 picsize（仅 pic）</label>
                            <input id="picsize" class="input" name="picsize" placeholder="纯数字，例如 300" />
                        </div>
                        <div class="field">
                            <label class="label" for="br">最高音质 br（仅 song，仅网易云有效）</label>
                            <input id="br" class="input" name="br" placeholder="纯数字，例如 1411" />
                        </div>
                        <div class="field">
                            <label class="label" for="yrc">逐字歌词 yrc</label>
                            <select id="yrc" name="yrc">
                                <option value="false" selected>False（默认禁用）</option>
                                <option value="true">True（启用）</option>
                                <option value="open">Open（备用启用模式）</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="handsome">Handsome 兼容模式</label>
                            <select id="handsome" name="handsome">
                                <option value="false" selected>false（默认）</option>
                                <option value="true">true（song/playlist 返回 cover 字段）</option>
                            </select>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn"><span class="material-symbols-outlined" style="vertical-align: middle;">play_arrow</span> 调用接口</button>
                            <button type="button" id="loadToPlayer" class="btn outline" disabled><span class="material-symbols-outlined" style="vertical-align: middle;">queue_music</span> 加载到播放器</button>
                        </div>
                    </form>
                    <div style="margin-top: 12px;" class="mono">请求地址：<span id="reqUrl"></span></div>
                    <div style="margin-top: 12px;">
                        <strong>返回结果</strong>
                        <div id="result" class="result-box">（等待调用）</div>
                    </div>
                </div>
            </div>

            <!-- 参数说明（保留所有内容） -->
            <div class="card">
                <div class="card-header">参数说明</div>
                <div class="card-content">
                    <div class="section">
                        <div class="label">server：数据源</div>
                        <div>• netease 网易云音乐（默认）</div>
                        <div>• tencent QQ音乐</div>
                    </div>
                    <div class="section">
                        <div class="label">type：类型</div>
                        <div>• name 歌曲名</div>
                        <div>• artist 歌手</div>
                        <div>• url 链接</div>
                        <div>• pic 封面</div>
                        <div>• lrc 歌词</div>
                        <div>• song 单曲</div>
                        <div>• playlist 歌单</div>
                        <div>• search 搜索</div>
                    </div>
                    <div class="section">
                        <div class="label">id：类型ID（封面ID/单曲ID/歌单ID）</div>
                        <div>（id 必须指定，在使用其它功能比如搜索时，请将 id 指定为 0）</div>
                    </div>
                    <div class="section">
                        <div class="label">picsize：歌曲封面大小（仅使用封面功能时 可选 携带，指定纯数字）</div>
                    </div>
                    <div class="section">
                        <div class="label">keyword：搜索关键词（仅使用搜索功能时携带）</div>
                    </div>
                    <div class="section">
                        <div class="label">br：歌曲最高音质（仅使用单曲功能时 可选 携带，目前仅网易云有效，指定纯数字，如 1411 即 1411kbps）</div>
                    </div>
                    <div class="section">
                        <div class="label">yrc：网易云音乐逐字歌词解析开关，开启后优先解析逐字歌词</div>
                        <div>• False 禁用（默认）</div>
                        <div>• True 启用</div>
                        <div>• Open 备用启用模式（该模式在没有逐字歌词时不会返回逐行歌词，而是返回空）</div>
                    </div>
                    <div class="section">
                        <div class="label">handsome：Handsome 主题兼容模式（可选）</div>
                        <div>• true 启用（song 和 playlist 返回 cover 字段而非 pic）</div>
                    </div>

                    <div class="section">
                        <div class="note">⚠️ Handsome 主题用户请注意</div>
                        <div>由于 Handsome 使用的并非标准的 Meting API 接口，需要进行特殊处理。请在 Handsome 主题的<b>开发者高级设置</b>中填写以下内容：</div>
                        <code class="mono handsome-code" style="display:block; padding:10px; background:#FAFAFA; border-radius:8px; margin-top:8px;">
                            {"music_api":"<?php echo API_URI ?>?server=:server&type=:type&id=:id&handsome=true"}
                        </code>
                    </div>

                    <div class="section example-list">
                        <div class="label">示例</div>
                        <a href="<?php echo API_URI ?>?server=netease&type=url&id=1969519579" target="_blank"><?php echo API_URI ?>?server=netease&type=url&id=1969519579</a><br />
                        <a href="<?php echo API_URI ?>?server=netease&type=song&id=1969519579" target="_blank"><?php echo API_URI ?>?server=netease&type=song&id=1969519579</a><br />
                        <a href="<?php echo API_URI ?>?server=netease&type=playlist&id=8900628861&yrc=true" target="_blank"><?php echo API_URI ?>?server=netease&type=playlist&id=8900628861&yrc=true</a><br />
                        <a href="<?php echo API_URI ?>?server=netease&type=search&id=0&yrc=true&keyword=寄往未来的信" target="_blank"><?php echo API_URI ?>?server=netease&type=search&id=0&yrc=true&keyword=寄往未来的信</a>
                    </div>

                    <div class="footer">
                        GitHub：<a href="https://github.com/liuran001/meting-api" target="_blank">meting-api</a>，此 API 基于 <a href="https://github.com/metowolf/Meting" target="_blank">Meting</a> 构建。
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="copyright">
        <div>
            © 2023 - <span id="currentYear">2026</span> 
            <a href="https://t.me/s/BDovo_Channel" target="_blank" rel="noopener noreferrer">笨蛋ovo</a> 
            All rights reserved.
        </div>
        <div style="margin-top: 8px;">
            <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">湘ICP备2024076649号</a>
        </div>
    </footer>

    <script>
        // 主题切换与持久化
        function getSystemPrefersDark() {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('meting_theme', theme);
            const icon = document.getElementById('themeIcon');
            if (icon) icon.textContent = (theme === 'dark' ? 'light_mode' : 'dark_mode');
        }
        function initTheme() {
            const saved = localStorage.getItem('meting_theme');
            const theme = saved ? saved : (getSystemPrefersDark() ? 'dark' : 'light');
            applyTheme(theme);
            const btn = document.getElementById('themeToggle');
            if (btn) {
                btn.addEventListener('click', () => {
                    const current = document.documentElement.getAttribute('data-theme') || 'light';
                    applyTheme(current === 'dark' ? 'light' : 'dark');
                });
            }
        }

        // 解析接口返回，适配 QQ 音乐 JSONP 的 @handle
        function normalizeToAPlayer(data) {
            if (!Array.isArray(data) || data.length === 0) return [];
            if (typeof data[0].url === 'string' && data[0].url.startsWith('@')) {
                const parts = data[0].url.split('@').slice(1);
                const url = parts[3];
                return fetchJsonp(url).then(r => r.json()).then(jsonpData => {
                    const domain = (
                        jsonpData.req_0.data.sip.find(i => !i.startsWith('http://ws')) ||
                        jsonpData.req_0.data.sip[0]
                    ).replace('http://', 'https://');
                    return data.map((v, i) => ({
                        name: v.name || v.title,
                        artist: v.artist || v.author,
                        url: domain + jsonpData.req_0.data.midurlinfo[i].purl,
                        cover: v.cover || v.pic,
                        lrc: v.lrc,
                    }));
                });
            }
            return Promise.resolve(data.map(v => ({
                name: v.name || v.title,
                artist: v.artist || v.author,
                url: v.url,
                cover: v.cover || v.pic,
                lrc: v.lrc,
            })));
        }

        let apInstance = null;
        async function initDefaultPlayer() {
            try {
                const res = await fetch(`<?php echo API_URI ?>?server=netease&type=playlist&id=8900628861&yrc=false`);
                const json = await res.json();
                const playlist = await normalizeToAPlayer(json);
                apInstance = new APlayer({
                    container: document.getElementById('aplayer'),
                    audio: playlist,
                    autoplay: false,
                    fixed: false,
                    volume: 0.7,
                    mutex: true,
                    loop: 'all',
                    order: 'random',
                    preload: 'auto',
                    lrcType: 3,
                });
            } catch (e) {
                console.error(e);
            }
        }

        function buildUrl(params) {
            const q = new URLSearchParams();
            Object.keys(params).forEach(k => {
                if (params[k] !== undefined && params[k] !== '') q.append(k, params[k]);
            });
            return `<?php echo API_URI ?>?` + q.toString();
        }

        async function callApi(formData) {
            const url = buildUrl(formData);
            document.getElementById('reqUrl').textContent = url;
            const resultEl = document.getElementById('result');
            const type = formData.type;
            
            try {
                const res = await fetch(url, { redirect: 'follow' });
                const contentType = res.headers.get('content-type') || '';
                
                // 特殊处理：url类型显示音频播放器
                if (type === 'url') {
                    const audioUrl = res.url; // 重定向后的实际URL
                    resultEl.className = 'result-box media-content';
                    resultEl.innerHTML = `
                        <audio controls style="width: 100%; margin: 0 0 12px 0;">
                            <source src="${audioUrl}" type="audio/mpeg">
                            您的浏览器不支持音频播放。
                        </audio>
                        <div style="word-break: break-all;">
                            <strong>音频地址：</strong><br/>
                            <a href="${audioUrl}" target="_blank" style="color: var(--md-primary);">${audioUrl}</a>
                        </div>
                    `;
                    document.getElementById('loadToPlayer').disabled = true;
                    return;
                }
                
                // 特殊处理：pic类型显示图片
                if (type === 'pic') {
                    const picUrl = res.url; // 重定向后的实际URL
                    resultEl.className = 'result-box media-content';
                    resultEl.innerHTML = `
                        <a href="${picUrl}" target="_blank" style="display: block; text-align: center; margin-bottom: 12px;">
                            <img src="${picUrl}" alt="封面图片" style="max-width: 100%; max-height: 400px; border-radius: 8px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" />
                        </a>
                        <div style="word-break: break-all; text-align: center;">
                            <strong>图片地址：</strong><br/>
                            <a href="${picUrl}" target="_blank" style="color: var(--md-primary);">${picUrl}</a>
                        </div>
                    `;
                    document.getElementById('loadToPlayer').disabled = true;
                    return;
                }
                
                if (contentType.includes('application/json')) {
                    const json = await res.json();
                    resultEl.className = 'result-box';
                    resultEl.textContent = JSON.stringify(json, null, 2);
                    const loadBtn = document.getElementById('loadToPlayer');
                    // 只有返回 song/playlist 的 JSON 时允许加载到播放器
                    if (Array.isArray(json) && (type === 'song' || type === 'playlist')) {
                        loadBtn.disabled = false;
                        loadBtn.onclick = async () => {
                            const playlist = await normalizeToAPlayer(json);
                            if (apInstance) {
                                apInstance.destroy();
                            }
                            apInstance = new APlayer({
                                container: document.getElementById('aplayer'),
                                audio: playlist,
                                autoplay: true,
                                fixed: false,
                                volume: 0.7,
                                mutex: true,
                                loop: 'all',
                                order: 'list',
                                preload: 'auto',
                                lrcType: 3,
                            });
                        };
                    } else {
                        loadBtn.disabled = true;
                        loadBtn.onclick = null;
                    }
                } else {
                    // 其他文本类型（如 lrc, name, artist）
                    resultEl.className = 'result-box';
                    resultEl.textContent = await res.text();
                    document.getElementById('loadToPlayer').disabled = true;
                }
            } catch (e) {
                resultEl.textContent = '请求失败：' + e.message;
            }
        }

        // 刷新统计数据
        async function refreshStats() {
            const countEl = document.getElementById('count');
            const oldText = countEl.textContent;
            try {
                countEl.textContent = '···';
                const r = await fetch('/meting/query.php?type=total');
                countEl.textContent = await r.text();
            } catch (e) {
                countEl.textContent = oldText;
            }
        }

        window.addEventListener('DOMContentLoaded', async () => {
            initTheme();
            await initDefaultPlayer();
            // 设置当前年份
            document.getElementById('currentYear').textContent = new Date().getFullYear();
            // 统计次数
            await refreshStats();

            // 点击刷新统计
            const statsBtn = document.getElementById('statsRefresh');
            if (statsBtn) {
                statsBtn.addEventListener('click', refreshStats);
            }

            // 前端解密邮箱地址（防爬虫）
            const emailParts = ['bdovo', '@', 'bdovo', '.', 'cc'];
            const emailEl = document.getElementById('email-placeholder');
            if (emailEl) {
                const email = emailParts.join('');
                emailEl.innerHTML = `<a href="mailto:${email}" style="color: var(--md-primary); text-decoration: none;">${email}</a>`;
            }

            const form = document.getElementById('testForm');
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const formData = {
                    server: document.getElementById('server').value,
                    type: document.getElementById('type').value,
                    id: document.getElementById('id').value,
                    keyword: document.getElementById('keyword').value,
                    picsize: document.getElementById('picsize').value,
                    br: document.getElementById('br').value,
                    yrc: document.getElementById('yrc').value,
                    handsome: document.getElementById('handsome').value,
                };
                await callApi(formData);
            });
        });
    </script>
</body>

</html>
