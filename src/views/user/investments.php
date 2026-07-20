<?php
// src/views/user/investments.php - My Investments Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = __("AeroPay - My Investments");
$description = __("Manage active yield contracts and watch daily ads to get your returns");
$activePage = "investments";

ob_start();
?>

<div class="marketplace-grid" id="investments-list" style="margin-bottom: 40px;">
    <!-- Investments dynamically loaded -->
</div>

<div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
    <h3 style="font-weight: 700; margin-bottom: 20px;"><?= __('Yield Earnings History') ?></h3>
    <div id="investment-history-list" style="display: flex; flex-direction: column; gap: 12px;">
        <!-- Earnings list dynamically loaded -->
    </div>
</div>

<!-- Ad Watch Theater Video Overlay -->
<div class="theater-mode" id="ad-theater" style="display: none;">
    <div class="video-container">
        <video id="ad-video-element" controls autoplay style="display: none;"></video>
        <!-- Supports YouTube, Vimeo, Instagram, Facebook, TikTok, Dailymotion, Twitter/X, and any embeddable URL -->
        <iframe id="ad-iframe-element" style="display: none;" allow="autoplay; encrypted-media; fullscreen; picture-in-picture; accelerometer; gyroscope" allowfullscreen referrerpolicy="no-referrer-when-downgrade" sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-presentation" frameborder="0"></iframe>

        <div class="countdown-overlay">
            ⏱️ <span id="ad-time-left">120</span>s <?= __('Remaining') ?>
        </div>
        <!-- Fallback if iframe/video fails to load -->
        <div id="ad-fallback" style="display: none; text-align: center; padding: 24px;">
            <p style="color: var(--muted); margin-bottom: 12px; font-size: 0.9rem;"><?= __('Video cannot be embedded directly. Watch it in a new tab:') ?></p>
            <a id="ad-fallback-link" href="#" target="_blank" rel="noopener noreferrer" class="btn-primary" style="display: inline-block; padding: 12px 24px; text-decoration: none; color: #000;"><?= __('🔗 Watch Video in New Tab') ?></a>
            <p style="color: var(--muted); margin-top: 12px; font-size: 0.75rem;"><?= __('The countdown timer will continue running. Come back to claim your reward once complete.') ?></p>
        </div>
    </div>
    <div class="claim-button-container" id="ad-claim-box" style="display: none;">
        <button class="btn-primary" onclick="claimDailyReward()">🎁 <?= __('Claim Daily Reward') ?></button>
    </div>
    <button class="btn-secondary" onclick="closeAdPlayer()" style="margin-top: 16px; padding: 10px 20px;"><?= __('Cancel') ?></button>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let activeInvestmentId = null;
    let activeAdClaimToken = null;
    let adCountdownTimer = null;
    let iframeLoadTimeout = null;

    /**
     * Universal video URL → embeddable URL converter.
     * Supports: YouTube (watch, shorts, live, embed), Vimeo, Dailymotion, Instagram (reel, p, tv),
     * Facebook (video, reel, watch, fb.watch), Twitter/X (via twitframe), TikTok, and direct video files.
     * Returns { type: 'iframe' | 'video' | 'fallback', url: string, originalUrl: string }
     */
    function getEmbedUrl(url) {
        const originalUrl = url;
        try {
            const u = new URL(url);
            const host = u.hostname.replace('www.', '').replace('m.', '');

            // ── YouTube ──
            if (host === 'youtube.com' || host === 'youtu.be' || host === 'youtube-nocookie.com') {
                let videoId = null;
                let playlistId = null;

                if (host === 'youtu.be') {
                    videoId = u.pathname.slice(1).split('/')[0].split('?')[0];
                } else if (u.pathname.startsWith('/embed/')) {
                    videoId = u.pathname.split('/embed/')[1]?.split(/[?/]/)[0];
                } else if (u.pathname.startsWith('/shorts/')) {
                    videoId = u.pathname.split('/shorts/')[1]?.split(/[?/]/)[0];
                } else if (u.pathname.startsWith('/live/')) {
                    videoId = u.pathname.split('/live/')[1]?.split(/[?/]/)[0];
                } else if (u.searchParams.has('v')) {
                    videoId = u.searchParams.get('v');
                }
                
                if (u.searchParams.has('list')) {
                    playlistId = u.searchParams.get('list').trim();
                }

                if (videoId) {
                    videoId = videoId.trim();
                    if (/^[a-zA-Z0-9_-]{10,12}$/.test(videoId)) {
                        // If it's part of a playlist, include the list param
                        const listParam = playlistId ? `&list=${playlistId}` : '';
                        return { type: 'iframe', url: `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1${listParam}`, originalUrl };
                    }
                } else if (playlistId) {
                    // Pure playlist link (no specific video)
                    return { type: 'iframe', url: `https://www.youtube-nocookie.com/embed/videoseries?list=${playlistId}&autoplay=1&rel=0&modestbranding=1`, originalUrl };
                }

                return { type: 'fallback', url: url, originalUrl };
            }

            // ── Vimeo ──
            if (host === 'vimeo.com' || host === 'player.vimeo.com') {
                const vimeoId = u.pathname.match(/\/(\d+)/)?.[1];
                if (vimeoId) {
                    return { type: 'iframe', url: `https://player.vimeo.com/video/${vimeoId.trim()}?autoplay=1`, originalUrl };
                }
                return { type: 'fallback', url: url, originalUrl };
            }

            // ── Dailymotion ──
            if (host === 'dailymotion.com' || host === 'dai.ly') {
                let dmId = null;
                if (host === 'dai.ly') {
                    dmId = u.pathname.slice(1).split('?')[0];
                } else {
                    dmId = u.pathname.match(/\/video\/([a-zA-Z0-9]+)/)?.[1];
                    if (!dmId) dmId = u.pathname.match(/\/embed\/video\/([a-zA-Z0-9]+)/)?.[1];
                }
                if (dmId) {
                    return { type: 'iframe', url: `https://www.dailymotion.com/embed/video/${dmId.trim()}?autoplay=1`, originalUrl };
                }
                return { type: 'fallback', url: url, originalUrl };
            }

            // ── Instagram Reels / Posts / TV ──
            if (host === 'instagram.com') {
                const igMatch = u.pathname.match(/\/(reel|p|tv)\/([A-Za-z0-9_-]+)/);
                if (igMatch) {
                    // Use /embed/captioned/ for better cross-origin support in production
                    return { type: 'iframe', url: `https://www.instagram.com/${igMatch[1]}/${igMatch[2]}/embed/captioned/`, originalUrl };
                }
                // Unsupported Instagram URL (stories, etc.) → open in new tab
                return { type: 'fallback', url: url, originalUrl };
            }

            // ── Facebook Videos (including Reels, Watch, fb.watch) ──
            if (host === 'facebook.com' || host === 'fb.watch' || host === 'fb.com') {
                return { type: 'iframe', url: `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(url)}&autoplay=true&show_text=false`, originalUrl };
            }

            // ── TikTok ──
            if (host === 'tiktok.com' || host === 'vm.tiktok.com') {
                const tiktokMatch = u.pathname.match(/\/video\/(\d+)/);
                if (tiktokMatch) {
                    return { type: 'iframe', url: `https://www.tiktok.com/embed/v2/${tiktokMatch[1]}`, originalUrl };
                }
                // Short TikTok URLs or unsupported formats → open in new tab
                return { type: 'fallback', url: url, originalUrl };
            }

            // ── Twitter / X ──
            if (host === 'twitter.com' || host === 'x.com') {
                // Twitter doesn't support direct iframe embeds for video.
                // Use twitframe.com as a public embed proxy.
                return { type: 'iframe', url: `https://twitframe.com/show?url=${encodeURIComponent(url)}`, originalUrl };
            }

            // ── Direct video files (.mp4, .webm, .ogg, .mov, .m4v, .avi) ──
            const ext = u.pathname.split('.').pop()?.toLowerCase();
            if (['mp4', 'webm', 'ogg', 'mov', 'm4v', 'avi'].includes(ext)) {
                return { type: 'video', url: url, originalUrl };
            }

            // ── Fallback: try iframe for any unknown URL ──
            return { type: 'iframe', url: url, originalUrl };

        } catch (e) {
            // If URL parsing fails, offer as fallback link
            return { type: 'fallback', url: url, originalUrl: url };
        }
    }

    /**
     * Show the "Watch in new tab" fallback UI when an embed cannot load.
     */
    function showFallbackLink(originalUrl) {
        const iframeEl = document.getElementById('ad-iframe-element');
        const videoEl = document.getElementById('ad-video-element');
        const fallbackEl = document.getElementById('ad-fallback');
        const fallbackLink = document.getElementById('ad-fallback-link');

        iframeEl.style.display = 'none';
        videoEl.style.display = 'none';
        fallbackEl.style.display = 'block';
        fallbackLink.href = originalUrl;
    }

    async function fetchInvestments() {
        try {
            const [data, histData] = await Promise.all([
                apiRequest('/api/products/my-investments'),
                apiRequest('/api/products/investment-history')
            ]);
            const list = document.getElementById('investments-list');
            list.innerHTML = '';

            if (data.investments.length === 0) {
                list.innerHTML = '<div class="glass-card" style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);"><?= __('You do not have any active investment contracts. Buy yield assets from the Marketplace to get started.') ?></div>';
            }

            data.investments.forEach(inv => {
                // Compute days remaining
                const exp = new Date(inv.expiresAt);
                const now = new Date();
                const daysRemaining = Math.max(0, Math.ceil((exp.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)));
                
                // Daily percentage reward
                const dailyReward = Math.floor((inv.purchasePrice * parseFloat(inv.dailyRewardPercent)) / 100);

                const card = document.createElement('div');
                card.className = 'glass-card';
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-weight: 700;">${inv.name}</h3>
                        <span class="badge-roi" style="background: var(--primary-glow); color: var(--primary);">${inv.dailyRewardPercent}<?= __('% ROI') ?></span>
                    </div>
                    <img src="${inv.imageUrl}" alt="${inv.name}" style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px;">
                    
                    <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 6px;"><?= __('Daily Yield:') ?> <strong style="color: var(--foreground);">${formatRupees(dailyReward)}</strong></p>
                    <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 12px;"><?= __('Contract Expiry:') ?> <strong>${daysRemaining}<?= __(' Days remaining') ?></strong></p>
                    
                    <div style="display: flex; gap: 12px;">
                        ${inv.watchedToday 
                            ? `<button class="btn-secondary" style="flex: 1; border-color: var(--success); color: var(--success); cursor: default;" disabled>✓ <?= __('Completed') ?></button>`
                            : `<button class="btn-primary" onclick="watchAd('${inv.id}', ${dailyReward}, ${inv.adWatchSeconds})" style="flex: 1;"><?= __('Watch Ad') ?></button>`
                        }
                        <button class="btn-destructive" onclick="exitInvestmentEarly('${inv.id}', '${inv.name}', ${inv.purchasePrice})" style="padding: 10px 14px;"><?= __('Exit') ?></button>
                    </div>
                `;
                list.appendChild(card);
            });

            // Load investment earnings logs
            const logContainer = document.getElementById('investment-history-list');
            logContainer.innerHTML = '';

            // Combine buys, rewards, and sells
            const combinedLogs = [];
            histData.buys.forEach(b => combinedLogs.push({ ...b, type: 'buy' }));
            histData.rewards.forEach(r => combinedLogs.push({ ...r, type: 'reward' }));
            histData.sells.forEach(s => combinedLogs.push({ ...s, type: 'sell' }));

            combinedLogs.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt));

            if (combinedLogs.length === 0) {
                logContainer.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;"><?= __('No logs found.') ?></div>';
            }

            combinedLogs.slice(0, 10).forEach(log => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                const isCredit = log.type !== 'buy';
                const color = isCredit ? 'var(--success)' : 'var(--destructive)';
                const prefix = isCredit ? '+' : '-';
                const label = log.type === 'buy' ? `<?= __('Purchased') ?> ${log.name}` : (log.type === 'sell' ? `<?= __('Early Refund') ?> ${log.name}` : `<?= __('Daily Ad Reward:') ?> ${log.name}`);
                const date = new Date(log.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });

                row.innerHTML = `
                    <div>
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--foreground);">${label}</span>
                        <span style="font-size: 0.7rem; color: var(--muted); display: block;">${date}</span>
                    </div>
                    <span style="font-weight: 800; color: ${color};">${prefix}${formatRupees(log.amount)}</span>
                `;
                logContainer.appendChild(row);
            });
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    async function exitInvestmentEarly(id, name, price) {
        const confirmExit = confirm(`<?= __('Are you sure you want to terminate "') ?>${name}<?= __('" contract early? Your initial purchase principal of ') ?>${formatRupees(price)}<?= __(' will be immediately refunded to your wallet balance.') ?>`);
        if (!confirmExit) return;

        try {
            await apiRequest(`/api/products/sell/${id}`, { method: 'POST' });
            Toast.show('<?= __('Contract exited. Balance refunded.') ?>');
            fetchInvestments();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    async function watchAd(investmentId, rewardAmount, seconds) {
        try {
            const data = await apiRequest(`/api/products/ad-url/${investmentId}`);
            activeInvestmentId = investmentId;
            activeAdClaimToken = data.claimToken;
            
            const theater = document.getElementById('ad-theater');
            const videoEl = document.getElementById('ad-video-element');
            const iframeEl = document.getElementById('ad-iframe-element');
            const claimBox = document.getElementById('ad-claim-box');
            const fallbackEl = document.getElementById('ad-fallback');

            videoEl.style.display = 'none';
            iframeEl.style.display = 'none';
            claimBox.style.display = 'none';
            fallbackEl.style.display = 'none';
            theater.style.display = 'flex';

            // Clear any previous iframe load timeout
            clearTimeout(iframeLoadTimeout);

            // Convert any video URL to an embeddable format
            const url = data.videoUrl;
            const embedInfo = getEmbedUrl(url);

            if (embedInfo.type === 'fallback') {
                // Immediately show fallback for URLs we know can't be embedded
                showFallbackLink(embedInfo.originalUrl);
            } else if (embedInfo.type === 'iframe') {
                iframeEl.src = embedInfo.url;
                iframeEl.style.display = 'block';

                // Set a timeout: if iframe doesn't load in 8 seconds, show fallback
                iframeLoadTimeout = setTimeout(() => {
                    // Check if iframe loaded successfully by trying to access it
                    try {
                        // If the iframe is still essentially empty/blocked, show fallback
                        const iframeDoc = iframeEl.contentDocument || iframeEl.contentWindow?.document;
                        if (!iframeDoc || !iframeDoc.body || iframeDoc.body.innerHTML === '') {
                            showFallbackLink(embedInfo.originalUrl);
                        }
                    } catch (e) {
                        // Cross-origin iframe - this is expected for YouTube/Vimeo etc.
                        // If we get a cross-origin error, it means the iframe DID load something
                        // so we do nothing (embed is working)
                    }
                }, 8000);

                // Also listen for iframe load errors
                iframeEl.onerror = () => {
                    clearTimeout(iframeLoadTimeout);
                    showFallbackLink(embedInfo.originalUrl);
                };
            } else {
                videoEl.src = embedInfo.url;
                videoEl.style.display = 'block';
                videoEl.play().catch(() => {
                    // If autoplay fails (e.g., browser policy), show fallback
                    showFallbackLink(embedInfo.originalUrl);
                });

                // Handle video load error
                videoEl.onerror = () => {
                    showFallbackLink(embedInfo.originalUrl);
                };
            }

            // Start countdown timer
            let timeLeft = seconds;
            document.getElementById('ad-time-left').innerText = timeLeft;

            clearInterval(adCountdownTimer);
            adCountdownTimer = setInterval(() => {
                timeLeft--;
                document.getElementById('ad-time-left').innerText = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(adCountdownTimer);
                    claimBox.style.display = 'block';
                    Toast.show('<?= __('Daily ad watched completely! Click Claim Reward.') ?>', 'success');
                }
            }, 1000);

        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    function closeAdPlayer() {
        clearInterval(adCountdownTimer);
        clearTimeout(iframeLoadTimeout);
        document.getElementById('ad-theater').style.display = 'none';
        document.getElementById('ad-video-element').src = '';
        document.getElementById('ad-iframe-element').src = '';
        document.getElementById('ad-fallback').style.display = 'none';
        activeInvestmentId = null;
        activeAdClaimToken = null;
    }

    // Confetti script helper
    function startConfettiAnimation() {
        const canvas = document.getElementById('confetti-canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        canvas.style.display = 'block';

        const particles = [];
        for (let i = 0; i < 150; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height - canvas.height,
                size: Math.random() * 8 + 4,
                color: `hsl(${Math.random() * 360}, 80%, 60%)`,
                speedX: Math.random() * 4 - 2,
                speedY: Math.random() * 5 + 3,
                rotation: Math.random() * 360
            });
        }

        function update() {
            ctx.clearRect(0,0, canvas.width, canvas.height);
            let active = false;
            particles.forEach(p => {
                p.y += p.speedY;
                p.x += p.speedX;
                p.rotation += 2;
                if (p.y < canvas.height) {
                    active = true;
                    ctx.fillStyle = p.color;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rotation * Math.PI / 180);
                    ctx.fillRect(-p.size/2, -p.size/2, p.size, p.size);
                    ctx.restore();
                }
            });

            if (active) {
                requestAnimationFrame(update);
            } else {
                canvas.style.display = 'none';
            }
        }
        update();
    }

    async function claimDailyReward() {
        if (!activeInvestmentId || !activeAdClaimToken) return;

        try {
            await apiRequest(`/api/products/watch/${activeInvestmentId}`, {
                method: 'POST',
                body: { claimToken: activeAdClaimToken }
            });
            closeAdPlayer();
            startConfettiAnimation();
            Toast.show('<?= __('Reward credited to wallet!') ?>', 'success');
            fetchInvestments();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchInvestments);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
