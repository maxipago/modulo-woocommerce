# Importante

Também é possível fazer o download
da [última release](https://github.com/DevelopersRede/woocommerce/releases/latest/download/rede-woocommerce.zip). Essa
versão já contém as dependências, então basta descompactar o pacote e enviá-lo para o servidor da plataforma.

# Módulo WooCommerce

Esse módulo foi testado até o WordPress 5.6 e WooCommerce 4.9. Esse módulo suporta os seguintes tipos de transação:

1. Pré autorização com cartão de crédito
2. Autorização com captura com cartão de crédito
3. Cartão de crédito com 3DS
4. Cartão de débito
5. Boleto bancário
6. Transferência eletrônica

## Requisitos

Os requisitos desse módulo são os mesmos requisitos do próprio WooCommerce e, por motivos de segurança, o PHP >= 7.3. Ou seja,
se o WordPress suportar o WooCommerce 4.9 e o servidor possuir o PHP >= 7.3, ele suportará o módulo.

# Instalação

Também é possível fazer o download da [última release](https://github.com/maxipago/modulo-woocommerce/releases/latest).
Nesse caso, ela já contém as dependências e o diretório rede-woocommerce pode ser enviado diretamente para sua
instalação do WooCommerce.

# Docker

Caso esteja desenvolvendo, o módulo contém uma imagem com o WordPress, WooCommerce/Storefront e o módulo da maxiPago!.
Tudo o que você precisa fazer é clonar esse repositório e fazer:

```
docker-compose up
```


### Instalação

**Etapa 1 - Backup dos dados**

Por questão de boas práticas realize o backup da loja e banco de dados antes de fazer qualquer tipo de instalação.

**Etapa 2 - Instalando o módulo e.Rede**

Após realizar o download do arquivo siga as seguintes instruções:

* Descompacte o conteúdo do arquivo dentro da pasta wp-content/plugins;
* Na área administrativa da loja vá até Plugins > Installed Plugins e ative o módulo WooCommerce maxiPago!;

**Etapa 3 – Configurando o módulo**

Após a instalação, navegue até menu _WooCommerce > Settings > Payments_ e habilite o módulo.
