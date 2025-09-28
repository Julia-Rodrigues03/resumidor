            document.getElementById('formResumo').addEventListener('submit', async function(event) {
            event.preventDefault(); // Impede o envio padrão do formulário

            const form = event.target;
            const formData = new FormData(form);
            const statusDiv = document.getElementById('status');
            const resultadoDiv = document.getElementById('resultado');

            // Mostra o status e esconde o resultado anterior
            statusDiv.style.display = 'block';
            statusDiv.textContent = 'Processando... Por favor, aguarde.';
            resultadoDiv.style.display = 'none';

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData // Envia o formulário com texto e/ou arquivo
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Ocorreu um erro na requisição.');
                }

                const data = await response.json();
                
                // Exibe o resultado
                document.getElementById('textoResumo').textContent = data.resumo;
                resultadoDiv.style.display = 'block';
                statusDiv.style.display = 'none';

            } catch (error) {
                statusDiv.textContent = 'Erro: ' + error.message;
                statusDiv.style.color = 'red';
            }
        });

