# Usar imagem PHP com Apache
FROM php:8.2-apache

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Instalar extensões PHP necessárias (se necessário)
# RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar arquivos do projeto para o diretório do Apache
COPY . /var/www/html/

# Definir permissões
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expor porta 80
EXPOSE 80

# Comando padrão (já está configurado na imagem base)
CMD ["apache2-foreground"]

