# Dockerfile para aplicação PHP com Apache
FROM php:8.2-apache

# Instalar extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    curl \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Configurar diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos da aplicação
COPY . /var/www/html/

# Script para criar keys.env a partir de variáveis de ambiente
RUN echo '#!/bin/bash' > /entrypoint.sh && \
    echo 'if [ ! -f /var/www/html/keys.env ]; then' >> /entrypoint.sh && \
    echo '    echo "# MasterPag API Keys" > /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "MASTERPAG_PUBLIC_KEY=${MASTERPAG_PUBLIC_KEY:-}" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "MASTERPAG_SECRET_KEY=${MASTERPAG_SECRET_KEY:-}" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "# MyTrust API Key" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "MYTRUST_API_KEY=${MYTRUST_API_KEY:-}" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "# Checkify API Key" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "CHECKIFY_KEY=${CHECKIFY_KEY:-}" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "# TechByNet API Key" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    echo "TECHBYNET_API_KEY=${TECHBYNET_API_KEY:-}" >> /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    chown www-data:www-data /var/www/html/keys.env' >> /entrypoint.sh && \
    echo '    chmod 600 /var/www/html/keys.env' >> /entrypoint.sh && \
    echo 'fi' >> /entrypoint.sh && \
    echo 'exec apache2-foreground' >> /entrypoint.sh && \
    chmod +x /entrypoint.sh

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Criar arquivo orders.json se não existir e dar permissões
RUN touch /var/www/html/orders.json \
    && chown www-data:www-data /var/www/html/orders.json \
    && chmod 666 /var/www/html/orders.json

# Configurar Apache para permitir .htaccess e definir index.html como padrão
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.html index.php\n\
</Directory>' > /etc/apache2/sites-available/000-default.conf

# Expor porta 80
EXPOSE 80

# Usar entrypoint personalizado
ENTRYPOINT ["/entrypoint.sh"]

