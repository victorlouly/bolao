// Frontend script to call PHP backend at /api.php
async function gerarPix(valor, cotas) {
    const nome = localStorage.getItem("nome_cpf") || "Cliente";
    const cpf = localStorage.getItem("cpf_value") || "00011122233";
    const email = localStorage.getItem("email_cpf") || "";
    const telefone = localStorage.getItem("telefone_cpf") || "";

    console.log('Iniciando geração de PIX...', { valor, cotas, nome, cpf, email, telefone });

    // CAMINHO CORRETO: Como app.js está em /js/, usa ../ para subir
    const apiUrl = '../api.php';
    
    console.log('URL da API:', apiUrl);

    try {
        const resp = await fetch(apiUrl, {
            method: "POST",
            headers: { 
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ 
                valor: parseFloat(valor), 
                cotas: cotas, 
                nome: nome, 
                cpf: cpf,
                email: email,
                telefone: telefone
            })
        });

        console.log('Status da resposta:', resp.status);
        console.log('URL requisitada:', resp.url);
        
        // Ler como texto primeiro
        const textResponse = await resp.text();
        console.log('Resposta recebida (primeiros 200 chars):', textResponse.substring(0, 200));

        // Tentar parsear como JSON
        let data;
        try {
            data = JSON.parse(textResponse);
            console.log('JSON parseado com sucesso!');
        } catch (parseError) {
            console.error('❌ Erro ao parsear JSON:', parseError);
            console.error('Texto completo recebido:', textResponse);
            return {
                success: false,
                error: "parse_error",
                message: "Erro ao processar resposta do servidor"
            };
        }

        return data;

    } catch (err) {
        console.error("❌ Erro ao chamar api.php:", err);
        return {
            success: false,
            error: "network_error",
            message: "Erro de conexão: " + err.message
        };
    }
}

$(document).ready(function () {

    $(".open-modal").click(async function (event) {
        event.preventDefault();

        // Verificar se o usuário fez login
        const nome = localStorage.getItem("nome_cpf");
        const cpf = localStorage.getItem("cpf_value");
        
        console.log('📋 Dados do localStorage:', { nome, cpf });

        if (!nome) {
            alert("Por favor, faça login primeiro.");
            return;
        }

        // Clear previous modal content
        $("#pixArea").empty();
        $("#continueToPayment").text("Gerando PIX...");
        $("#continueToPayment").removeAttr("href");
        $("#continueToPayment").removeClass("btn-success btn-danger").addClass("btn-primary");

        const valor = $(this).data("valor");
        const cotas = $(this).data("cotas");

        console.log('💰 Gerando PIX:', { valor, cotas, nome });

        $("#paymentModal").fadeIn();

        const transacao = await gerarPix(valor, cotas);

        console.log('📦 Resposta completa da transação:', transacao);

        // Verificar se tem PIX na resposta
        if (transacao && transacao.pix && transacao.pix.qrcode) {
            const qrcode = transacao.pix.qrcode;
            const expiration = transacao.pix.expirationDate || "Não informado";
            const valorFormatado = (transacao.amount / 100).toFixed(2).replace('.', ',');

            console.log('✅ PIX gerado com sucesso!');

            // Show QR and code
            $("#pixArea").html(`
                <div style="text-align:center;">
                    <p style="font-weight:bold;color:#28a745;margin-bottom:15px;">✓ PIX gerado com sucesso!</p>
                    <p style="margin-bottom:10px;">Valor: <strong>R$ ${valorFormatado}</strong></p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(qrcode)}" 
                         style="border-radius:8px;border:2px solid #ddd;margin-bottom:10px;"
                         alt="QR Code PIX">
                    <p style="font-size:12px;margin-bottom:5px;color:#666;">Código PIX Copia e Cola:</p>
                    <textarea id="pixCode" readonly style="width:100%;height:80px;font-size:11px;padding:8px;border:1px solid #ddd;border-radius:4px;resize:none;">${qrcode}</textarea>
                    <div style="margin-top:10px;">
                        <button id="copyPix" class="btn btn-success" style="width:100%;">
                            <i class="bi bi-clipboard"></i> Copiar Código PIX
                        </button>
                    </div>
                    <p style="font-size:12px;margin-top:10px;color:#dc3545;">
                        <i class="bi bi-clock"></i> Válido até: <strong>${expiration}</strong>
                    </p>
                    <p style="font-size:11px;color:#666;margin-top:8px;">
                        Abra o app do seu banco e escolha "Pagar com PIX"
                    </p>
                </div>
            `);

            $("#continueToPayment").text("✓ PAGAMENTO GERADO");
            $("#continueToPayment").removeClass("btn-primary btn-danger").addClass("btn-success");

            // Função de copiar
            $("#copyPix").click(function () {
                const txt = $("#pixCode").val();
                
                // Tentar usar a API moderna
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(txt).then(() => { 
                        $(this).html('<i class="bi bi-check-circle"></i> Copiado!');
                        $(this).removeClass('btn-success').addClass('btn-primary');
                        
                        setTimeout(() => {
                            $(this).html('<i class="bi bi-clipboard"></i> Copiar Código PIX');
                            $(this).removeClass('btn-primary').addClass('btn-success');
                        }, 2000);
                    }).catch(() => {
                        copiarFallback(txt);
                    });
                } else {
                    copiarFallback(txt);
                }
            });

            // Função fallback para copiar
            function copiarFallback(text) {
                const textarea = $("#pixCode")[0];
                textarea.select();
                textarea.setSelectionRange(0, 99999);
                
                try {
                    document.execCommand('copy');
                    $("#copyPix").html('<i class="bi bi-check-circle"></i> Copiado!');
                    $("#copyPix").removeClass('btn-success').addClass('btn-primary');
                    
                    setTimeout(() => {
                        $("#copyPix").html('<i class="bi bi-clipboard"></i> Copiar Código PIX');
                        $("#copyPix").removeClass('btn-primary').addClass('btn-success');
                    }, 2000);
                } catch (err) {
                    alert('Não foi possível copiar. Por favor, copie manualmente.');
                }
            }

        } else {
            // Erro ao gerar PIX
            const errorMsg = transacao?.message || transacao?.error || "Não foi possível gerar o pagamento";
            console.error('❌ Erro ao gerar PIX:', transacao);
            
            $("#continueToPayment").text("✗ Erro ao gerar pagamento");
            $("#continueToPayment").removeClass("btn-primary btn-success").addClass("btn-danger");
            
            $("#pixArea").html(`
                <div style="text-align:center;">
                    <p class='text-danger' style="font-weight:bold;">
                        <i class="bi bi-exclamation-triangle"></i> ${errorMsg}
                    </p>
                    <p style="font-size:12px;color:#666;margin-top:10px;">
                        Tente novamente em instantes ou entre em contato com o suporte.
                    </p>
                    <button class="btn btn-outline-primary mt-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Tentar Novamente
                    </button>
                </div>
            `);
        }
    });

    // Fechar modal
    $(".modal-close").click(function () {
        $("#paymentModal").fadeOut();
    });

    // Fechar modal ao clicar fora
    $(window).click(function (event) {
        if ($(event.target).is("#paymentModal")) {
            $("#paymentModal").fadeOut();
        }
    });

});