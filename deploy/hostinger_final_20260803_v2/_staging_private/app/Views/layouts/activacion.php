<?php

$tituloPagina = $titulo ?? 'Activar cuenta';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?> · PsicoMatch</title>
    <style>
        :root {
            --pm-sage: #657166;
            --pm-aqua: #99CDD8;
            --pm-mint: #DAEBE3;
            --pm-peach: #FDE8D3;
            --pm-coral: #F3C3B2;
            --pm-olive: #CFD6C4;
            --pm-white: #FFFFFF;
            --pm-bg: #F7F9F8;
            --pm-soft: #F8F9FA;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--pm-sage);
            background:
                radial-gradient(circle at 12% 18%, var(--pm-mint), transparent 42%),
                radial-gradient(circle at 88% 10%, var(--pm-peach), transparent 38%),
                linear-gradient(160deg, var(--pm-bg), var(--pm-soft) 55%, var(--pm-olive));
        }
        .activacion-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .activacion-card {
            width: min(100%, 440px);
            background: var(--pm-white);
            border: 1px solid rgba(101, 113, 102, 0.12);
            border-radius: 1.25rem;
            padding: 2rem 1.75rem;
            box-shadow: 0 18px 48px rgba(101, 113, 102, 0.12);
        }
        .activacion-brand {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            text-decoration: none;
            color: var(--pm-sage);
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: .02em;
            margin-bottom: 1.25rem;
        }
        .activacion-brand span {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--pm-mint), var(--pm-aqua));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
        }
        h1 {
            margin: 0 0 .5rem;
            font-size: 1.55rem;
            font-weight: 700;
        }
        .activacion-lead {
            margin: 0 0 1.25rem;
            line-height: 1.55;
            color: #5d6860;
        }
        .activacion-meta {
            margin: 0 0 1.25rem;
            padding: .9rem 1rem;
            border-radius: .85rem;
            background: var(--pm-mint);
            font-size: .95rem;
            line-height: 1.45;
        }
        label {
            display: block;
            margin: 0 0 .35rem;
            font-size: .92rem;
            font-weight: 600;
        }
        input[type="password"] {
            width: 100%;
            border: 1px solid rgba(101, 113, 102, 0.28);
            border-radius: .7rem;
            padding: .75rem .9rem;
            margin-bottom: .9rem;
            font: inherit;
            background: var(--pm-soft);
            color: var(--pm-sage);
        }
        input[type="password"]:focus {
            outline: 2px solid var(--pm-aqua);
            border-color: transparent;
            background: var(--pm-white);
        }
        .activacion-reqs {
            margin: 0 0 1.1rem;
            padding-left: 1.1rem;
            color: #6a746d;
            font-size: .88rem;
            line-height: 1.45;
        }
        .activacion-btn {
            width: 100%;
            border: 0;
            border-radius: .8rem;
            padding: .85rem 1rem;
            background: linear-gradient(135deg, var(--pm-aqua), #7fb8c5);
            color: #334038;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .activacion-btn:disabled {
            opacity: .65;
            cursor: not-allowed;
        }
        .activacion-alert {
            margin: 0 0 1rem;
            padding: .85rem 1rem;
            border-radius: .8rem;
            background: var(--pm-coral);
            color: #5a4038;
            line-height: 1.45;
        }
        .activacion-link {
            display: inline-block;
            margin-top: 1rem;
            color: var(--pm-sage);
            text-decoration: underline;
        }
        .pm-aviso-simplificado {
            margin: 1rem 0 1.2rem;
            padding: .9rem 1rem;
            border-radius: .8rem;
            background: rgba(255,255,255,.55);
            border: 1px solid rgba(101,113,102,.18);
            text-align: left;
        }
        .pm-aviso-simplificado h3 {
            margin: 0 0 .55rem;
            font-size: 1rem;
        }
        .pm-aviso-simplificado p {
            margin: 0 0 .7rem;
            font-size: .9rem;
            line-height: 1.45;
        }
        .pm-aviso-simplificado a { color: var(--pm-sage); }
        .form-check {
            display: flex;
            gap: .55rem;
            align-items: flex-start;
            margin: 0 0 .65rem;
            font-size: .9rem;
            line-height: 1.4;
        }
        .form-check-input { margin-top: .2rem; }
    </style>
</head>
<body>
    <main class="activacion-wrap">
        <?php require $content; ?>
    </main>
</body>
</html>
