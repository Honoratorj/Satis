<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesquisa Enviada com Sucesso</title>
<style>
    :root { --primary: #ad1a05; --accent: #f98224; }
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
        padding: 16px;
        text-align: center;
        background: radial-gradient(circle at top, #ffffff 0%, #f7f8fa 40%, #f3f5f7 100%);
        overflow: hidden;
    }
    #confetti { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }
    .container {
        position: relative;
        z-index: 2;
        background: #fff;
        padding: 40px 32px;
        border-radius: 24px;
        box-shadow: 0 20px 50px rgba(16, 24, 40, 0.12);
        max-width: 420px;
        width: 100%;
        opacity: 0;
        transform: translateY(30px) scale(0.96);
        animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }
    @keyframes rise { to { opacity: 1; transform: translateY(0) scale(1); } }

    .check {
        width: 96px;
        height: 96px;
        margin: 0 auto 18px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--accent));
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 12px 28px rgba(173, 26, 5, 0.28);
        animation: pop 0.6s 0.25s cubic-bezier(0.22, 1.4, 0.4, 1) both, glow 2.4s 0.9s ease-in-out infinite;
    }
    @keyframes pop { 0% { transform: scale(0); } 60% { transform: scale(1.15); } 100% { transform: scale(1); } }
    @keyframes glow {
        0%, 100% { box-shadow: 0 12px 28px rgba(173, 26, 5, 0.28); }
        50% { box-shadow: 0 12px 40px rgba(249, 130, 36, 0.5); }
    }

    .check svg { width: 52px; height: 52px; }
    .check svg path {
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        animation: draw 0.5s 0.55s ease forwards;
    }
    @keyframes draw { to { stroke-dashoffset: 0; } }

    h2 { color: var(--primary); margin: 0 0 8px; font-size: 1.5rem; }
    .message { margin-top: 6px; font-size: 1.05rem; color: #555; line-height: 1.5; }
    .footer { margin-top: 24px; font-size: 0.82rem; color: #999; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; }
        .container { opacity: 1; transform: none; }
        .check svg path { stroke-dashoffset: 0; }
    }
</style>
</head>
<body>
    <canvas id="confetti" aria-hidden="true"></canvas>
    <div class="container">
        <div class="check" aria-hidden="true">
            <svg viewBox="0 0 52 52" fill="none">
                <path d="M14 27l8 8 16-18" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Pesquisa enviada com sucesso!</h2>
        <div class="message">Obrigado pela sua avaliação. Sua opinião ajuda a melhorar o nosso atendimento.</div>
        <div class="footer">&copy; 2026 Consórcio Monto Mendes Júnior. Todos os direitos reservados.</div>
    </div>

    <script>
    (function () {
        "use strict";
        if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        var canvas = document.getElementById("confetti");
        var ctx = canvas.getContext("2d");
        var dpr = Math.min(window.devicePixelRatio || 1, 2);
        var colors = ["#ad1a05", "#f98224", "#ffd27a", "#ff6b4a", "#2ecc71"];
        var parts = [];
        function fit() {
            canvas.width = window.innerWidth * dpr;
            canvas.height = window.innerHeight * dpr;
            canvas.style.width = window.innerWidth + "px";
            canvas.style.height = window.innerHeight + "px";
        }
        fit();
        window.addEventListener("resize", fit);

        function spawn(count, cx, cy) {
            for (var i = 0; i < count; i++) {
                var a = Math.random() * Math.PI * 2;
                var sp = (Math.random() * 7 + 3) * dpr;
                parts.push({
                    x: cx, y: cy,
                    vx: Math.cos(a) * sp,
                    vy: Math.sin(a) * sp - 5 * dpr,
                    s: (Math.random() * 9 + 5) * dpr,
                    color: colors[(Math.random() * colors.length) | 0],
                    rot: Math.random() * Math.PI,
                    vr: (Math.random() - 0.5) * 0.35,
                    life: Math.random() * 40 + 70
                });
            }
        }

        function loop() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (var i = parts.length - 1; i >= 0; i--) {
                var p = parts[i];
                p.vy += 0.12 * dpr;
                p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                p.life--;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.globalAlpha = Math.max(0, Math.min(1, p.life / 60));
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.s / 2, -p.s / 2, p.s, p.s * 0.6);
                ctx.restore();
                if (p.life <= 0 || p.y > canvas.height + 40) parts.splice(i, 1);
            }
            if (parts.length) requestAnimationFrame(loop);
            else ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        var cx = canvas.width / 2, cy = canvas.height / 3;
        spawn(120, cx, cy);
        setTimeout(function () { spawn(60, canvas.width * 0.25, cy); }, 250);
        setTimeout(function () { spawn(60, canvas.width * 0.75, cy); }, 450);
        requestAnimationFrame(loop);
    })();
    </script>
</body>
</html>
