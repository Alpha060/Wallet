<?php
// src/views/user/investments.php - My Investments Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = "AeroPay - My Investments";
$description = "Manage active yield contracts and watch daily ads to get your returns";
$activePage = "investments";

ob_start();
?>

<div class="marketplace-grid" id="investments-list" style="margin-bottom: 40px;">
    <!-- Investments dynamically loaded -->
</div>

<div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
    <h3 style="font-weight: 700; margin-bottom: 20px;">Yield Earnings History</h3>
    <div id="investment-history-list" style="display: flex; flex-direction: column; gap: 12px;">
        <!-- Earnings list dynamically loaded -->
    </div>
</div>

<!-- Ad Watch Theater Video Overlay -->
<div class="theater-mode" id="ad-theater" style="display: none;">
    <div class="video-container">
        <video id="ad-video-element" controls autoplay style="display: none;"></video>
        <!-- In case it is an embedded YouTube URL or other link, we support iframe as fallback -->
        <iframe id="ad-iframe-element" style="display: none;" allow="autoplay"></iframe>

        <div class="countdown-overlay">
            ⏱️ <span id="ad-time-left">120</span>s Remaining
        </div>
    </div>
    <div class="claim-button-container" id="ad-claim-box" style="display: none;">
        <button class="btn-primary" onclick="claimDailyReward()">🎁 Claim Daily Reward</button>
    </div>
    <button class="btn-secondary" onclick="closeAdPlayer()" style="margin-top: 16px; padding: 10px 20px;">Cancel</button>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let activeInvestmentId = null;
    let activeAdClaimToken = null;
    let adCountdownTimer = null;
    
    async function fetchInvestments() {
        try {
            const [data, histData] = await Promise.all([
                apiRequest('/api/products/my-investments'),
                apiRequest('/api/products/investment-history')
            ]);
            const list = document.getElementById('investments-list');
            list.innerHTML = '';

            if (data.investments.length === 0) {
                list.innerHTML = '<div class="glass-card" style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">You do not have any active investment contracts. Buy yield assets from the Marketplace to get started.</div>';
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
                        <span class="badge-roi" style="background: var(--primary-glow); color: var(--primary);">${inv.dailyRewardPercent}% ROI</span>
                    </div>
                    <img src="${inv.imageUrl}" alt="${inv.name}" style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px;">
                    
                    <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 6px;">Daily Yield: <strong style="color: var(--foreground);">${formatRupees(dailyReward)}</strong></p>
                    <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 12px;">Contract Expiry: <strong>${daysRemaining} Days remaining</strong></p>
                    
                    <div style="display: flex; gap: 12px;">
                        ${inv.watchedToday 
                            ? `<button class="btn-secondary" style="flex: 1; border-color: var(--success); color: var(--success); cursor: default;" disabled>✓ Completed</button>`
                            : `<button class="btn-primary" onclick="watchAd('${inv.id}', ${dailyReward}, ${inv.adWatchSeconds})" style="flex: 1;">Watch Ad</button>`
                        }
                        <button class="btn-destructive" onclick="exitInvestmentEarly('${inv.id}', '${inv.name}', ${inv.purchasePrice})" style="padding: 10px 14px;">Exit</button>
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
                logContainer.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No logs found.</div>';
            }

            combinedLogs.slice(0, 10).forEach(log => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                const isCredit = log.type !== 'buy';
                const color = isCredit ? 'var(--success)' : 'var(--destructive)';
                const prefix = isCredit ? '+' : '-';
                const label = log.type === 'buy' ? `Purchased ${log.name}` : (log.type === 'sell' ? `Early Refund ${log.name}` : `Daily Ad Reward: ${log.name}`);
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
        const confirmExit = confirm(`Are you sure you want to terminate "${name}" contract early? Your initial purchase principal of ${formatRupees(price)} will be immediately refunded to your wallet balance.`);
        if (!confirmExit) return;

        try {
            await apiRequest(`/api/products/sell/${id}`, { method: 'POST' });
            Toast.show('Contract exited. Balance refunded.');
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

            videoEl.style.display = 'none';
            iframeEl.style.display = 'none';
            claimBox.style.display = 'none';
            theater.style.display = 'flex';

            // Check link type
            const url = data.videoUrl;
            if (url.includes('youtube.com') || url.includes('youtu.be') || url.includes('vimeo.com')) {
                // Handle iframe embeds
                let embedUrl = url;
                if (url.includes('watch?v=')) {
                    embedUrl = url.replace('watch?v=', 'embed/');
                }
                iframeEl.src = embedUrl;
                iframeEl.style.display = 'block';
            } else {
                // Handle raw video files
                videoEl.src = url;
                videoEl.style.display = 'block';
                videoEl.play().catch(() => {});
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
                    Toast.show('Daily ad watched completely! Click Claim Reward.', 'success');
                }
            }, 1000);

        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    function closeAdPlayer() {
        clearInterval(adCountdownTimer);
        document.getElementById('ad-theater').style.display = 'none';
        document.getElementById('ad-video-element').src = '';
        document.getElementById('ad-iframe-element').src = '';
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
            Toast.show('Reward credited to wallet!', 'success');
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
