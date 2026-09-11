<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/msg_store.php';

$token = msg_clean_token((string) ($_GET['token'] ?? ''));
$card  = null;
if ($token !== '') {
    $store = msg_read_store();
    $card  = msg_find_by_token($store, $token);
}

if (!is_array($card)) {
    http_response_code(404);
}

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$povLine       = is_array($card) ? h(trim((string) ($card['povLine'] ?? '')))       : '';
$recipientName = is_array($card) ? h(trim((string) ($card['recipientName'] ?? ''))) : '';
$theme         = is_array($card) ? (string) ($card['theme'] ?? 'rose')              : 'rose';
$lines         = is_array($card) ? (array) ($card['lines'] ?? [])                   : [];

$themes = [
    'rose' => ['--c1:#ff6b9d','--c2:#c0397a','--c3:#ffe0ef','--heart:#ff6b9d','--bg:#0d0010'],
    'blue' => ['--c1:#6b9dff','--c2:#3965e0','--c3:#e0eaff','--heart:#6b9dff','--bg:#00060d'],
    'gold' => ['--c1:#ffd166','--c2:#e09b20','--c3:#fff5d6','--heart:#ffd166','--bg:#0d0900'],
    'mint' => ['--c1:#66ffcc','--c2:#20c08a','--c3:#d6fff5','--heart:#66ffcc','--bg:#000d09'],
];
$themeVars = $themes[$theme] ?? $themes['rose'];
$cssVars   = implode(';', array_map(
    fn($k, $v) => $k . ':' . $v,
    array_keys($themeVars),
    array_values($themeVars)
));
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $povLine ? 'pov: ' . $povLine : 'کارت'; ?></title>
    <style>
        @font-face{font-family:'YekanBakh';font-style:normal;font-weight:400;font-display:swap;src:url('/fonts/YekanBakh-Regular.woff2') format('woff2'),url('/fonts/YekanBakh-Regular.woff') format('woff')}
        @font-face{font-family:'YekanBakh';font-style:normal;font-weight:700;font-display:swap;src:url('/fonts/YekanBakh-Bold.woff2') format('woff2'),url('/fonts/YekanBakh-Bold.woff') format('woff')}
        @font-face{font-family:'B Nazanin';font-style:normal;font-weight:400;font-display:swap;src:url('/fonts/B Nazanin-.ttf') format('truetype')}
        @font-face{font-family:'B Nazanin';font-style:normal;font-weight:700;font-display:swap;src:url('/fonts/B Nazanin-.ttf') format('truetype')}
        @font-face{font-family:'B Nazanin';font-style:normal;font-weight:900;font-display:swap;src:url('/fonts/B Nazanin Bold-.ttf') format('truetype')}

        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

        :root { <?php echo $cssVars; ?> }

        html,body{
            min-height:100dvh;
            background:var(--bg);
            color:#fff;
            font-family:'YekanBakh',system-ui,sans-serif;
            -webkit-font-smoothing:antialiased;
            overflow-x:hidden;
        }

        /* ── canvas for particles ── */
        #canvas{
            position:fixed;inset:0;
            pointer-events:none;
            z-index:0;
        }

        /* ── card wrapper ── */
        .card{
            position:relative;z-index:1;
            min-height:100dvh;
            display:flex;flex-direction:column;
            align-items:center;justify-content:center;
            padding:3rem 1.5rem env(safe-area-inset-bottom,1.5rem);
            gap:0;
        }

        /* ── glow behind text ── */
        .glow{
            position:absolute;
            width:min(500px,90vw);height:min(500px,90vw);
            background:radial-gradient(circle,var(--c2) 0%,transparent 70%);
            opacity:.12;
            filter:blur(60px);
            pointer-events:none;
            z-index:0;
        }

        .inner{
            position:relative;z-index:1;
            display:flex;flex-direction:column;
            align-items:center;
            gap:2rem;
            max-width:560px;
            width:100%;
            text-align:center;
        }

        /* ── POV opener ── */
        .pov-label{
            font-size:.78rem;
            letter-spacing:.18em;
            text-transform:uppercase;
            color:var(--c1);
            opacity:0;
            animation:fadeUp .6s ease .2s forwards;
        }

        .pov-line{
            font-size:clamp(1.5rem,5vw,2.4rem);
            font-family:'B Nazanin','YekanBakh',sans-serif;
            font-weight:700;
            line-height:1.25;
            color:#fff;
            text-shadow:0 0 40px var(--c2);
            opacity:0;
            animation:fadeUp .8s ease .5s forwards;
        }

        /* ── story lines ── */
        .lines{
            display:flex;flex-direction:column;
            gap:1.1rem;
            width:100%;
        }

        .story-line{
            font-size:clamp(.95rem,3vw,1.15rem);
            color:rgba(255,255,255,.85);
            line-height:1.65;
            opacity:0;
        }

        /* ── recipient ── */
        .recipient{
            font-size:.85rem;
            color:var(--c1);
            letter-spacing:.06em;
            margin-top:1rem;
            opacity:0;
        }

        /* ── divider ── */
        .divider{
            width:3rem;height:2px;
            background:linear-gradient(90deg,transparent,var(--c1),transparent);
            opacity:0;
            animation:fadeUp .6s ease .9s forwards;
        }

        /* ── not found ── */
        .not-found{
            min-height:100dvh;
            display:flex;flex-direction:column;
            align-items:center;justify-content:center;
            gap:1rem;color:rgba(255,255,255,.4);
            font-size:.95rem;
        }
        .not-found-icon{font-size:3rem;display:block;}

        /* ── animations ── */
        @keyframes fadeUp{
            from{opacity:0;transform:translateY(20px)}
            to{opacity:1;transform:translateY(0)}
        }
    </style>
</head>
<body>

<canvas id="canvas"></canvas>

<?php if (is_array($card)): ?>
<div class="card">
    <div class="glow"></div>
    <div class="inner">
        <span class="pov-label">pov:</span>
        <h1 class="pov-line"><?php echo $povLine; ?></h1>

        <?php if (!empty($lines)): ?>
        <div class="divider"></div>
        <div class="lines" id="lines">
            <?php foreach ($lines as $i => $line): ?>
            <p class="story-line" data-index="<?php echo $i; ?>"><?php echo h((string)$line); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($recipientName !== ''): ?>
        <span class="recipient" id="recipient">— <?php echo $recipientName; ?></span>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    /* ── particle canvas ── */
    var canvas = document.getElementById('canvas');
    var ctx    = canvas.getContext('2d');
    var color  = getComputedStyle(document.documentElement).getPropertyValue('--c1').trim();
    var W, H, particles = [];

    function resize(){ W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    resize();
    window.addEventListener('resize', resize);

    function Particle(){
        this.reset();
    }
    Particle.prototype.reset = function(){
        this.x  = Math.random() * W;
        this.y  = H + 20;
        this.r  = Math.random() * 6 + 3;
        this.vx = (Math.random() - .5) * .6;
        this.vy = -(Math.random() * .8 + .4);
        this.a  = Math.random() * .6 + .2;
        this.da = -(Math.random() * .002 + .001);
    };
    Particle.prototype.update = function(){
        this.x += this.vx;
        this.y += this.vy;
        this.a += this.da;
        if(this.a <= 0 || this.y < -20) this.reset();
    };

    for(var i=0;i<28;i++){
        var p = new Particle();
        p.y = Math.random() * H;
        particles.push(p);
    }

    function drawHeart(ctx, x, y, r){
        ctx.save();
        ctx.translate(x, y);
        ctx.scale(r/10, r/10);
        ctx.beginPath();
        ctx.moveTo(0,-3);
        ctx.bezierCurveTo(0,-8,8,-8,8,-3);
        ctx.bezierCurveTo(8,2,0,8,0,10);
        ctx.bezierCurveTo(0,8,-8,2,-8,-3);
        ctx.bezierCurveTo(-8,-8,0,-8,0,-3);
        ctx.closePath();
        ctx.restore();
    }

    function loop(){
        ctx.clearRect(0,0,W,H);
        particles.forEach(function(p){
            p.update();
            ctx.save();
            ctx.globalAlpha = Math.max(0, p.a);
            ctx.fillStyle = color;
            drawHeart(ctx, p.x, p.y, p.r);
            ctx.fill();
            ctx.restore();
        });
        requestAnimationFrame(loop);
    }
    loop();

    /* ── stagger story lines ── */
    var storyLines = document.querySelectorAll('.story-line');
    var BASE_DELAY = 1200;
    storyLines.forEach(function(el, i){
        el.style.animation = 'fadeUp .7s ease ' + (BASE_DELAY + i * 280) + 'ms forwards';
    });

    var recipient = document.getElementById('recipient');
    if(recipient){
        recipient.style.animation = 'fadeUp .7s ease ' + (BASE_DELAY + storyLines.length * 280 + 300) + 'ms forwards';
    }
})();
</script>

<?php else: ?>
<div class="not-found">
    <span class="not-found-icon">💌</span>
    <p>این کارت در دسترس نیست یا حذف شده.</p>
</div>
<?php endif; ?>

</body>
</html>
