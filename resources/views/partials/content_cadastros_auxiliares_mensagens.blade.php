@if (($cadastrosAuxiliaresMensagensIntegracao ?? false))
    <div class="flash-message" data-cad-msg-container data-timeout="{{ (int) $cadastrosAuxiliaresMensagensTimeout }}">
        @foreach ($cadastrosAuxiliaresMensagens as $mensagem)
            @php
                $ativo = $mensagem['ativo'] ?? true;
                $publico = $mensagem['publico'] ?? null;
                $deveExibir = $ativo !== false;

                if ($deveExibir && is_bool($publico)) {
                    $deveExibir = $publico ? true : auth()->check();
                } elseif ($deveExibir && !empty($publico)) {
                    $publicos = is_array($publico)
                        ? $publico
                        : array_map('trim', explode(',', (string) $publico));

                    $publicosNormalizados = collect($publicos)
                        ->map(function ($item) {
                            $texto = mb_strtolower(trim((string) $item));
                            return str_replace('á', 'a', $texto);
                        })
                        ->filter()
                        ->values();

                    if ($publicosNormalizados->contains('usuario')) {
                        $deveExibir = auth()->check();
                    }
                }

                $classe = match($mensagem['tipo'] ?? 'info') {
                    'erro' => 'danger',
                    'aviso' => 'warning',
                    'sucesso' => 'success',
                    default => 'info',
                };
            @endphp

            @if ($deveExibir)
                <div class="alert alert-{{ $classe }} alert-dismissible" data-cad-msg-alert>
                    @if (!empty($mensagem['titulo']))
                        <strong>{{ $mensagem['titulo'] }}</strong><br>
                    @endif
                    {{ $mensagem['conteudo'] ?? '' }}
                    <button type="button" class="close" aria-label="Fechar" onclick="this.closest('[data-cad-msg-alert]').remove();">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
        @endforeach
    </div>

    <script>
      (function () {
        const container = document.querySelector('[data-cad-msg-container]');
        if (!container) return;

        const timeoutSeconds = Number(container.dataset.timeout || 0);
        const useAutoDismiss = timeoutSeconds > 0;

                const endpoint = @json(route('usp-theme.cadastros-auxiliares.mensagens-proxy'));
                const isAuth = @json(auth()->check());
                const refreshSeconds = Number(@json($cadastrosAuxiliaresMensagensRefresh ?? 30) || 30);
                const refreshMs = refreshSeconds * 1000;
                const timeoutMs = timeoutSeconds * 1000;

                let lastSignature = '';

                const removeLater = (alert) => {
                    if (!useAutoDismiss) {
                        return;
                    }

                    setTimeout(() => {
                        alert.remove();
                    }, timeoutMs);
                };

                const deveExibirMensagem = (mensagem) => {
                    if (mensagem.ativo === false) {
                        return false;
                    }

                    const publico = mensagem.publico;

                    if (publico === false) {
                        return isAuth;
                    }

                    if (Array.isArray(publico)) {
                        const normalizados = publico
                            .map((item) => String(item || '').trim().toLowerCase().replace('á', 'a'))
                            .filter(Boolean);

                        if (normalizados.includes('usuario')) {
                            return isAuth;
                        }
                    }

                    if (typeof publico === 'string' && publico.trim() !== '') {
                        const normalizados = publico
                            .split(',')
                            .map((item) => String(item || '').trim().toLowerCase().replace('á', 'a'))
                            .filter(Boolean);

                        if (normalizados.includes('usuario')) {
                            return isAuth;
                        }
                    }

                    return true;
                };

                const criarAlert = (mensagem) => {
                    const classe = ({
                        erro: 'danger',
                        aviso: 'warning',
                        sucesso: 'success',
                        info: 'info'
                    })[mensagem.tipo || 'info'] || 'info';

                    const alert = document.createElement('div');
                    alert.className = `alert alert-${classe} alert-dismissible`;
                    alert.setAttribute('data-cad-msg-alert', '1');

                    if (mensagem.titulo) {
                        const strong = document.createElement('strong');
                        strong.textContent = mensagem.titulo;
                        alert.appendChild(strong);
                        alert.appendChild(document.createElement('br'));
                    }

                    alert.appendChild(document.createTextNode(mensagem.conteudo || ''));

                    const closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'close';
                    closeButton.setAttribute('aria-label', 'Fechar');
                    closeButton.innerHTML = '<span aria-hidden="true">&times;</span>';
                    closeButton.addEventListener('click', () => alert.remove());
                    alert.appendChild(closeButton);

                    removeLater(alert);
                    return alert;
                };

                const renderizarMensagens = (mensagens) => {
                    const visiveis = mensagens.filter((mensagem) => deveExibirMensagem(mensagem));
                    const assinatura = JSON.stringify(visiveis.map((mensagem) => [
                        mensagem.id,
                        mensagem.updated_at,
                        mensagem.ativo,
                        mensagem.titulo,
                        mensagem.conteudo,
                        mensagem.tipo,
                        mensagem.publico,
                    ]));

                    const possuiAlertsRenderizados = container.querySelector('[data-cad-msg-alert]') !== null;

                    if (assinatura === lastSignature && possuiAlertsRenderizados) {
                        return;
                    }

                    lastSignature = assinatura;
                    container.innerHTML = '';

                    visiveis.forEach((mensagem) => {
                        container.appendChild(criarAlert(mensagem));
                    });
                };

                const existingAlerts = container.querySelectorAll('[data-cad-msg-alert]');
                existingAlerts.forEach((alert) => removeLater(alert));

                if (!endpoint) {
                    return;
                }

                const atualizarMensagens = () => {
                    const urlObj = new URL(endpoint, window.location.origin);
                    urlObj.searchParams.set('_t', String(Date.now()));
                    const url = urlObj.toString();

                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then((response) => (response.ok ? response.json() : []))
                        .then((mensagens) => {
                            if (!Array.isArray(mensagens)) {
                                return;
                            }

                            renderizarMensagens(mensagens);
                        })
                        .catch(() => {
                        });
                };

                atualizarMensagens();
                setInterval(atualizarMensagens, refreshMs);
      })();
    </script>
@endif
