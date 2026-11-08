<div class="toast-stack" id="toast-stack" aria-live="polite">
    @if (session('success') && ! ($ignorarSessao ?? false))
        <div class="toast toast--success" role="status">
            <span class="toast__texto">{{ session('success') }}</span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
    @if (session('error') && ! ($ignorarSessao ?? false))
        <div class="toast toast--error" role="alert">
            <span class="toast__texto">{{ session('error') }}</span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
    @if (session('warning') && ! ($ignorarSessao ?? false))
        <div class="toast toast--warning" role="alert">
            <span class="toast__texto">{{ session('warning') }}</span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
    @if (isset($errors) && $errors->any() && ! ($ignorarErros ?? false))
        <div class="toast toast--error" role="alert">
            <span class="toast__texto">
                @foreach ($errors->all() as $erro)
                    <span class="toast__linha">{{ $erro }}</span>
                @endforeach
            </span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
</div>
<script>
(function () {
    function stack() {
        var el = document.getElementById('toast-stack');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast-stack';
            el.className = 'toast-stack';
            el.setAttribute('aria-live', 'polite');
        }
        if (el.parentNode !== document.body) {
            document.body.appendChild(el);
        }
        return el;
    }

    function fechar(toast) {
        if (!toast || !toast.parentNode) return;
        toast.style.transition = 'opacity 0.3s';
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 300);
    }

    // Ícone por tipo, com o traço desenhado na entrada.
    var TIPOS = ['success', 'error', 'warning', 'info'];
    var ICONES = {
        success: '<path d="M7.5 12.5l3 3 6-6.5"/>',
        error: '<path d="M9 9l6 6M15 9l-6 6"/>',
        warning: '<path d="M12 7.5v5.5M12 16.5v.01"/>',
        info: '<path d="M12 11v5.5M12 7.5v.01"/>'
    };

    function colocarIcone(toast) {
        if (toast.querySelector('.toast__icone')) return;
        var tipo = TIPOS.filter(function (t) { return toast.classList.contains('toast--' + t); })[0] || 'info';
        var icone = document.createElement('span');
        icone.className = 'toast__icone';
        icone.setAttribute('aria-hidden', 'true');
        icone.innerHTML = '<svg viewBox="0 0 24 24" focusable="false"><circle class="toast__icone-aro" cx="12" cy="12" r="10"/>' + ICONES[tipo] + '</svg>';
        toast.insertBefore(icone, toast.firstChild);
    }

    // Fecha sozinho; com o mouse em cima o tempo pausa (a barra embaixo mostra quanto falta).
    function agendar(toast, duracao) {
        var restante = duracao || (toast.classList.contains('toast--error') ? 6000 : 4500);
        var inicio = 0;
        var relogio = null;
        var barra = document.createElement('span');
        barra.className = 'toast__tempo';
        barra.style.animationDuration = restante + 'ms';
        toast.appendChild(barra);

        function contar() {
            inicio = Date.now();
            relogio = setTimeout(function () { fechar(toast); }, restante);
        }
        toast.addEventListener('mouseenter', function () {
            clearTimeout(relogio);
            restante -= Date.now() - inicio;
            toast.classList.add('is-pausado');
        });
        toast.addEventListener('mouseleave', function () {
            toast.classList.remove('is-pausado');
            contar();
        });
        contar();
    }

    function ligarFechar(toast) {
        var btn = toast.querySelector('.toast__fechar');
        if (!btn || btn.dataset.ligado) return;
        btn.dataset.ligado = '1';
        btn.addEventListener('click', function () { fechar(toast); });
    }

    // opcoes.duracao (ms) encurta avisos rápidos; opcoes.substituir troca o aviso anterior do mesmo tipo.
    window.mostrarToast = function (mensagem, tipo, opcoes) {
        var texto = String(mensagem || '').trim();
        if (!texto) return;
        tipo = tipo || 'success';
        opcoes = opcoes || {};
        var root = stack();
        if (tipo === 'error' || opcoes.substituir) {
            root.querySelectorAll('.toast--' + tipo).forEach(function (t) { t.remove(); });
        }
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + tipo;
        toast.setAttribute('role', tipo === 'success' ? 'status' : 'alert');
        var span = document.createElement('span');
        span.className = 'toast__texto';
        span.textContent = texto;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toast__fechar';
        btn.setAttribute('aria-label', 'Fechar');
        btn.textContent = '\u00d7';
        toast.appendChild(span);
        toast.appendChild(btn);
        colocarIcone(toast);
        root.appendChild(toast);
        ligarFechar(toast);
        agendar(toast, opcoes.duracao);
    };

    stack().querySelectorAll('.toast').forEach(function (toast) {
        colocarIcone(toast);
        ligarFechar(toast);
        agendar(toast);
    });
})();
</script>
