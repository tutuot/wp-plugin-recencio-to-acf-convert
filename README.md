# Plugin de Conversão Recencio → WordPress + ACF

## Sobre o plugin

Este plugin foi desenvolvido **por IA** para facilitar a migração das resenhas cadastradas no plugin **Recencio Book Reviews** para o formato de posts nativos do WordPress, utilizando os campos personalizados do **Advanced Custom Fields (ACF)**.

A ferramenta permite converter as resenhas existentes sem perder os registros originais do Recencio, criando novos posts que podem ser utilizados pelo tema personalizado do site Livros Para Ler.

## Versão

**1.2.1**

## Funcionalidades

- Identifica as resenhas cadastradas no post type `rcno_review` do Recencio.
- Converte as resenhas em posts nativos do WordPress.
- Transfere as informações para os campos correspondentes do ACF.
- Cria os novos posts como rascunhos.
- Define as categorias `reviews` e `impressa`.
- Define o campo `tipo_midia` como `livro`.
- Permite testar a conversão individualmente antes de executar a migração.
- Permite converter as resenhas em lotes de até 5 registros.
- Identifica resenhas já convertidas e evita duplicações.
- Preserva os registros originais do Recencio.

## Requisitos

- WordPress.
- Plugin Recencio Book Reviews, com as resenhas cadastradas.
- Advanced Custom Fields (ACF).
- Grupos de campos ACF configurados no site.

## Informações migradas

Os dados das resenhas são transferidos para os campos ACF correspondentes.

### Grupo MediaImpressa

| Campo ACF | Informação |
|---|---|
| `autor` | Autor do livro |
| `titulo` | Título |
| `paginas` | Número de páginas |
| `editora` | Editora |
| `serie` | Série literária |
| `volume` | Volume |
| `isbn` | ISBN |
| `capa` | Capa do livro |
| `ilustrador` | Ilustrador |
| `sinopse` | Sinopse |

### Grupo Review

| Campo ACF | Informação |
|---|---|
| `nota` | Nota da resenha |
| `link_amazon` | Link de afiliado ou compra |
| `spoiler` | Indicador de conteúdo com spoilers |
| `tipo_midia` | Tipo de mídia, definido como `livro` |

## Como utilizar

1. Instale e ative o plugin no WordPress.
2. Acesse a tela de gerenciamento do conversor.
3. Utilize a opção de teste individual para selecionar uma resenha e verificar os dados que serão transferidos.
4. Execute a conversão individual para validar o resultado.
5. Após confirmar que os dados estão corretos, utilize a conversão em lotes.
6. O plugin processa até 5 resenhas por lote.
7. Resenhas já convertidas são ignoradas nas próximas execuções.

Os posts criados ficam como **rascunhos**, permitindo revisar os dados e ajustar o conteúdo antes de publicá-los.

## Cuidados durante a migração

- Mantenha o Recencio instalado e ativo durante o processo de migração.
- Não exclua os registros originais antes de conferir os posts convertidos.
- Verifique especialmente as capas, notas, sinopses e links.
- Confira se as categorias e os campos ACF foram preenchidos corretamente.
- Faça um backup do site antes de executar operações de migração.

## Histórico de versões

### 1.2.1
- Correção do botão de execução da conversão em lotes.

### 1.2.0
- Implementação da conversão em lotes de até 5 resenhas.
- Identificação de registros já convertidos para evitar duplicações.

### 1.1.0
- Implementação da conversão individual.
- Seleção de uma resenha para teste.
- Pré-visualização dos campos antes da conversão.
- Criação do post convertido como rascunho.
- Preservação do registro original do Recencio.

### 1.0.0
- Primeira versão do conversor.

## Observações

O plugin foi desenvolvido especificamente para a estrutura de campos ACF e categorias utilizada pelo meu site.

Alterações nos nomes dos campos, grupos ACF ou categorias podem exigir ajustes no código do conversor.

A conversão não remove automaticamente as resenhas originais do Recencio.
