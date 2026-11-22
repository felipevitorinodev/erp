<?php

namespace Database\Seeders\Desempenho;

/**
 * Dados de referência da carga de desempenho: nomes, endereços da Paraíba e vizinhança,
 * produtos com marca, embalagem, NCM e preço de prateleira, equipamentos de assistência técnica
 * e despesas fixas de uma loja de varejo misto com assistência técnica em João Pessoa.
 */
final class Catalogo
{
    public const NOMES_MASCULINOS = [
        'José', 'João', 'Antônio', 'Francisco', 'Carlos', 'Paulo', 'Pedro', 'Lucas', 'Luiz', 'Marcos',
        'Luís', 'Gabriel', 'Rafael', 'Daniel', 'Marcelo', 'Bruno', 'Eduardo', 'Felipe', 'Raimundo', 'Rodrigo',
        'Manoel', 'Mateus', 'André', 'Fernando', 'Fábio', 'Leonardo', 'Gustavo', 'Guilherme', 'Leandro', 'Tiago',
        'Anderson', 'Ricardo', 'Márcio', 'Jorge', 'Sebastião', 'Alexandre', 'Roberto', 'Edson', 'Diego', 'Vitor',
        'Sérgio', 'Cláudio', 'Matheus', 'Severino', 'Joaquim', 'Davi', 'Arthur', 'Heitor', 'Bernardo', 'Samuel',
        'Enzo', 'Miguel', 'Wellington', 'Josué', 'Erivaldo', 'Genivaldo', 'Damião', 'Cícero', 'Ítalo', 'Renato',
        'Hugo', 'Caio', 'Igor', 'Otávio', 'Wesley', 'Jonas', 'Emanuel', 'Valdir', 'Ivanildo', 'Rivaldo',
    ];

    public const NOMES_FEMININOS = [
        'Maria', 'Ana', 'Francisca', 'Antônia', 'Adriana', 'Juliana', 'Márcia', 'Fernanda', 'Patrícia', 'Aline',
        'Sandra', 'Camila', 'Amanda', 'Bruna', 'Jéssica', 'Letícia', 'Júlia', 'Luciana', 'Vanessa', 'Mariana',
        'Gabriela', 'Vera', 'Vitória', 'Larissa', 'Cláudia', 'Beatriz', 'Luana', 'Rita', 'Sônia', 'Renata',
        'Eliane', 'Josefa', 'Simone', 'Natália', 'Raquel', 'Carla', 'Débora', 'Rosângela', 'Tatiana', 'Kelly',
        'Isabela', 'Larissa', 'Alice', 'Helena', 'Valentina', 'Laura', 'Manuela', 'Sophia', 'Lívia', 'Cecília',
        'Rayane', 'Thaís', 'Priscila', 'Edilene', 'Maria José', 'Maria das Graças', 'Ana Paula', 'Ana Clara', 'Joana', 'Socorro',
        'Lúcia', 'Marta', 'Elaine', 'Cristiane', 'Daniela', 'Fabiana', 'Gisele', 'Mônica', 'Regina', 'Silvana',
    ];

    public const SOBRENOMES = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes',
        'Costa', 'Ribeiro', 'Martins', 'Carvalho', 'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa',
        'Rocha', 'Dias', 'Nascimento', 'Andrade', 'Moreira', 'Nunes', 'Marques', 'Machado', 'Mendes', 'Freitas',
        'Cardoso', 'Ramos', 'Gonçalves', 'Santana', 'Teixeira', 'Araújo', 'Cavalcanti', 'Medeiros', 'Brito', 'Farias',
        'Cunha', 'Macedo', 'Bezerra', 'Batista', 'Monteiro', 'Pinto', 'Moura', 'Correia', 'Queiroga', 'Wanderley',
        'Nóbrega', 'Diniz', 'Maia', 'Tavares', 'Leite', 'Dantas', 'Galdino', 'Xavier', 'Sales', 'Campos',
        'Fonseca', 'Pessoa', 'Guedes', 'Coutinho', 'Albuquerque', 'Vasconcelos', 'Holanda', 'Brandão', 'Paiva', 'Lacerda',
        'Siqueira', 'Aragão', 'Bandeira', 'Figueiredo', 'Meira', 'Torres', 'Assis', 'Viana', 'Rangel', 'Porto',
    ];

    /** [cidade, UF, DDD, faixa de CEP, peso, bairros] */
    public const CIDADES = [
        ['João Pessoa', 'PB', '83', [58000, 58099], 52, [
            'Manaíra', 'Tambaú', 'Cabo Branco', 'Bessa', 'Aeroclube', 'Jardim Oceania', 'Bancários', 'Mangabeira',
            'Valentina de Figueiredo', 'Cristo Redentor', 'Torre', 'Tambiá', 'Centro', 'Expedicionários', 'Miramar',
            'Jaguaribe', 'Cruz das Armas', 'Oitizeiro', 'José Américo', 'Água Fria', 'Castelo Branco', 'Bairro dos Estados',
            'Altiplano', 'Portal do Sol', 'Geisel', 'Cuiá', 'Gramame', 'Torre', 'Roger', 'Mandacaru', 'Cristo Redentor',
        ]],
        ['Cabedelo', 'PB', '83', [58100, 58109], 6, ['Intermares', 'Camboinha', 'Ponta de Campina', 'Centro', 'Jardim Manguinhos', 'Formosa']],
        ['Bayeux', 'PB', '83', [58110, 58119], 5, ['Centro', 'Imaculada', 'Sesi', 'Alto da Boa Vista', 'Comercial Norte', 'Mário Andreazza']],
        ['Santa Rita', 'PB', '83', [58300, 58309], 5, ['Centro', 'Tibiri', 'Várzea Nova', 'Marcos Moura', 'Popular', 'Alto das Populares']],
        ['Conde', 'PB', '83', [58322, 58322], 2, ['Centro', 'Jacumã', 'Carapibus', 'Tabatinga']],
        ['Campina Grande', 'PB', '83', [58400, 58439], 12, ['Centro', 'Catolé', 'Prata', 'Liberdade', 'Bodocongó', 'Alto Branco', 'Malvinas', 'José Pinheiro', 'Mirante']],
        ['Guarabira', 'PB', '83', [58200, 58209], 2, ['Centro', 'Primavera', 'Novo', 'Bairro das Nações']],
        ['Patos', 'PB', '83', [58700, 58709], 2, ['Centro', 'Jatobá', 'Belo Horizonte', 'Salgadinho']],
        ['Mamanguape', 'PB', '83', [58280, 58280], 1, ['Centro', 'Planalto', 'Areia Branca']],
        ['Recife', 'PE', '81', [50000, 52999], 6, ['Boa Viagem', 'Casa Forte', 'Espinheiro', 'Graças', 'Madalena', 'Pina', 'Torre', 'Várzea']],
        ['Goiana', 'PE', '81', [55900, 55900], 1, ['Centro', 'Nova Goiana', 'Ponta de Pedras']],
        ['Natal', 'RN', '84', [59000, 59099], 4, ['Ponta Negra', 'Tirol', 'Petrópolis', 'Lagoa Nova', 'Capim Macio', 'Alecrim', 'Candelária']],
        ['Parnamirim', 'RN', '84', [59140, 59159], 1, ['Centro', 'Nova Parnamirim', 'Emaús', 'Cohabinal']],
    ];

    public const LOGRADOUROS = [
        'Avenida Epitácio Pessoa', 'Avenida Governador Flávio Ribeiro Coutinho', 'Avenida Cabo Branco', 'Avenida Almirante Tamandaré',
        'Avenida Presidente Epitácio', 'Avenida Rui Carneiro', 'Avenida Hilton Souto Maior', 'Avenida Josefa Taveira',
        'Avenida Dom Pedro II', 'Avenida Cruz das Armas', 'Avenida Beira Rio', 'Avenida Sapé', 'Avenida Pombal',
        'Rua das Trincheiras', 'Rua Duque de Caxias', 'Rua da Areia', 'Rua Visconde de Pelotas', 'Rua Rodrigues de Aquino',
        'Rua Joaquim Nabuco', 'Rua Silvino Lopes', 'Rua Infante Dom Henrique', 'Rua Coração de Jesus', 'Rua Bancário Sérgio Guerra',
        'Rua Josefa Taveira', 'Rua Tenente Lucena', 'Rua Monteiro Lobato', 'Rua Santos Dumont', 'Rua Frei Caneca',
        'Rua Treze de Maio', 'Rua Sete de Setembro', 'Rua Quinze de Novembro', 'Rua São José', 'Rua Santo Antônio',
        'Rua Professor Batista Leite', 'Rua Manoel Deodato', 'Rua Corálio Soares de Oliveira', 'Rua Maria Rosa',
        'Rua Edson Ramalho', 'Rua Golfo de Dioniso', 'Rua Carlos Alverga', 'Rua Esperidião Rosas', 'Rua Odon Bezerra',
        'Travessa Santa Luzia', 'Rua do Rosário', 'Rua Nossa Senhora dos Navegantes', 'Rua Projetada', 'Rua das Acácias',
        'Rua dos Ipês', 'Rua das Mangueiras', 'Rua dos Cajueiros', 'Rua Padre Ibiapina', 'Rua Getúlio Vargas',
    ];

    public const COMPLEMENTOS = ['Apto 101', 'Apto 202', 'Apto 304', 'Apto 1102', 'Casa A', 'Casa B', 'Bloco C', 'Sala 3', 'Loja 2', 'Fundos', 'Térreo'];

    public const RAMOS_CLIENTE_PJ = [
        'Mercadinho', 'Padaria', 'Restaurante', 'Lanchonete', 'Construtora', 'Farmácia', 'Auto Peças', 'Oficina Mecânica',
        'Escola', 'Clínica', 'Pousada', 'Academia', 'Salão de Beleza', 'Papelaria', 'Pizzaria', 'Frigorífico',
        'Distribuidora', 'Escritório de Contabilidade', 'Imobiliária', 'Hotel', 'Material de Construção', 'Pet Shop', 'Ótica', 'Igreja',
    ];

    public const NOMES_FANTASIA = [
        'São José', 'Bom Jesus', 'Nossa Senhora', 'Estrela', 'Atlântico', 'Sol Nascente', 'Tambaú', 'Manaíra', 'Cabo Branco',
        'Bessa', 'Nordeste', 'Paraíba', 'Sertão', 'Litoral', 'Primavera', 'Boa Vista', 'Central', 'Real', 'Ideal', 'Nova Era',
        'Progresso', 'Santa Rita', 'Esperança', 'Família', 'União', 'Ponta do Seixas', 'Jardim', 'Alvorada', 'Aurora', 'Brisa',
        'Mar Azul', 'Bom Preço', 'Vitória', 'Paraíso', 'Rio Paraíba', 'Mangabeira', 'Do Povo', 'Sabor da Terra', 'Raízes', 'Pôr do Sol',
    ];

    public const SUFIXOS_PJ = ['Ltda', 'Ltda', 'Ltda', 'ME', 'EIRELI', 'S/A'];

    /**
     * Produtos por departamento. Item: [nome base, marcas, [variação => preço], NCM, unidade, extras (combinações)?].
     * O preço muda até ±15% entre marcas; giro é o peso do departamento nas vendas; estoque é [mínimo, máximo].
     */
    public const DEPARTAMENTOS = [
        'Mercearia' => ['giro' => 34, 'estoque' => [24, 240], 'qtd' => [1, 6], 'icms' => '102', 'itens' => [
            ['Arroz', ['Tio João', 'Camil', 'Prato Fino', 'Kicaldo', 'Blue Ville', 'Pai João'], ['Branco Tipo 1 1kg' => 6.49, 'Branco Tipo 1 5kg' => 28.90, 'Parboilizado 1kg' => 6.99, 'Integral 1kg' => 8.49], '10063021', 'PCT'],
            ['Feijão', ['Camil', 'Kicaldo', 'Turquesa', 'Pai João', 'Namorado'], ['Carioca 1kg' => 8.99, 'Preto 1kg' => 9.49, 'Macassar 1kg' => 9.99, 'Verde 1kg' => 11.49], '07133399', 'PCT'],
            ['Café', ['Santa Clara', '3 Corações', 'Pilão', 'Melitta', 'São Braz', 'Maratá'], ['Tradicional 250g' => 12.90, 'Extra Forte 250g' => 13.49, 'Tradicional 500g' => 24.90, 'Gourmet 250g' => 21.90], '09012100', 'PCT'],
            ['Açúcar', ['União', 'Estrela', 'Olho d\'Água', 'Caeté', 'Alegre'], ['Cristal 1kg' => 4.79, 'Refinado 1kg' => 5.29, 'Demerara 1kg' => 6.99, 'Cristal 5kg' => 22.90], '17019900', 'PCT'],
            ['Macarrão', ['Vitarella', 'Fortaleza', 'Adria', 'Renata', 'Petybon', 'Dona Benta'], ['Espaguete 500g' => 4.49, 'Parafuso 500g' => 4.69, 'Penne 500g' => 4.69, 'Lasanha 500g' => 7.99, 'Talharim 500g' => 5.49], '19021900', 'PCT'],
            ['Biscoito', ['Vitarella', 'Fortaleza', 'Piraquê', 'Bauducco', 'Richester', 'Marilan'], ['Cream Cracker 400g' => 5.99, 'Maria 400g' => 5.49, 'Recheado Chocolate 130g' => 2.79, 'Wafer Morango 140g' => 3.49, 'Água e Sal 350g' => 5.29, 'Rosquinha Coco 400g' => 5.99], '19053100', 'PCT'],
            ['Flocão de Milho', ['São Braz', 'Maratá', 'Kimilho', 'Vitamilho'], ['500g' => 2.99, '1kg' => 5.49], '11042300', 'PCT'],
            ['Farinha de Trigo', ['Dona Benta', 'Sol', 'Rosa Branca', 'Finna'], ['Tradicional 1kg' => 5.99, 'Com Fermento 1kg' => 6.49], '11010010', 'PCT'],
            ['Farinha de Mandioca', ['Maratá', 'Yoki', 'São Braz'], ['Branca 1kg' => 6.99, 'Torrada 500g' => 4.99], '11062000', 'PCT'],
            ['Óleo de Soja', ['Soya', 'Liza', 'ABC', 'Cocamar'], ['900ml' => 7.99], '15079011', 'UN'],
            ['Azeite de Oliva', ['Gallo', 'Andorinha', 'Borges', 'La Violetera'], ['Extra Virgem 500ml' => 39.90, 'Extra Virgem 250ml' => 22.90], '15091000', 'UN'],
            ['Leite em Pó', ['Ninho', 'Itambé', 'Piracanjuba', 'CCGL', 'Molico'], ['Integral 400g' => 21.90, 'Integral 800g' => 41.90, 'Desnatado 300g' => 19.90], '04022110', 'PCT'],
            ['Leite UHT', ['Betânia', 'Piracanjuba', 'Italac', 'Parmalat', 'Camponesa'], ['Integral 1L' => 5.79, 'Desnatado 1L' => 5.99, 'Semidesnatado 1L' => 5.89], '04012010', 'UN'],
            ['Achocolatado', ['Nescau', 'Toddy', 'Itambé'], ['400g' => 9.99, '800g' => 18.90], '18069000', 'PCT'],
            ['Margarina', ['Qualy', 'Delícia', 'Doriana', 'Primor'], ['Com Sal 500g' => 9.49, 'Com Sal 250g' => 5.49], '15171000', 'UN'],
            ['Sardinha', ['Gomes da Costa', 'Coqueiro', '88'], ['Óleo 125g' => 6.49, 'Tomate 125g' => 6.49], '16041310', 'UN'],
            ['Atum', ['Gomes da Costa', 'Coqueiro'], ['Ralado Óleo 170g' => 9.99, 'Pedaços Óleo 170g' => 12.49], '16041410', 'UN'],
            ['Extrato de Tomate', ['Elefante', 'Fugini', 'Pomarola', 'Quero'], ['340g' => 5.49, 'Sachê 190g' => 2.79], '20029010', 'UN'],
            ['Molho de Tomate', ['Pomarola', 'Fugini', 'Quero', 'Heinz'], ['Tradicional 340g' => 3.49, 'Manjericão 340g' => 3.79], '21032010', 'UN'],
            ['Tempero', ['Sazón', 'Knorr', 'Maggi', 'Arisco'], ['Carne 60g' => 4.49, 'Frango 60g' => 4.49, 'Caldo de Galinha 57g' => 2.49, 'Completo 300g' => 6.99], '21039021', 'UN'],
            ['Sal Refinado', ['Cisne', 'Lebre', 'Norsal'], ['1kg' => 2.99], '25010020', 'PCT'],
            ['Goiabada', ['Predilecta', 'Cascão', 'Zélia'], ['Lata 600g' => 9.99, 'Tablete 300g' => 4.99], '20079990', 'UN'],
            ['Doce de Leite', ['Itambé', 'Piracanjuba', 'Aviação'], ['Pote 400g' => 11.90], '19019090', 'UN'],
            ['Cuscuz Pronto', ['Maratá', 'São Braz'], ['500g' => 3.99], '19049000', 'PCT'],
            ['Aveia', ['Quaker', 'Yoki', 'Nestlé'], ['Flocos Finos 200g' => 4.99, 'Flocos 450g' => 8.99], '11041200', 'UN'],
            ['Granola', ['Mãe Terra', 'Kobber', 'Jasmine'], ['Tradicional 800g' => 19.90, 'Sem Açúcar 250g' => 11.90], '19042000', 'PCT'],
            ['Café Solúvel', ['Nescafé', '3 Corações', 'Pilão'], ['Tradicional 100g' => 14.90, 'Matinal 100g' => 13.90], '21011110', 'UN'],
            ['Mistura para Bolo', ['Dona Benta', 'Fleischmann', 'Sol'], ['Chocolate 450g' => 5.99, 'Laranja 450g' => 5.99, 'Milho 450g' => 5.99], '19012000', 'PCT'],
        ]],
        'Bebidas' => ['giro' => 26, 'estoque' => [48, 480], 'qtd' => [1, 12], 'icms' => '500', 'itens' => [
            ['Refrigerante', ['Coca-Cola', 'Guaraná Antarctica', 'Fanta Laranja', 'Sprite', 'Pepsi', 'Kuat', 'Fanta Uva'], ['Lata 350ml' => 4.49, 'PET 600ml' => 5.99, 'PET 1L' => 7.49, 'PET 2L' => 10.99], '22021000', 'UN', [], '0301000'],
            ['Refrigerante Zero', ['Coca-Cola', 'Guaraná Antarctica', 'Pepsi'], ['Lata 350ml' => 4.69, 'PET 2L' => 11.49], '22021000', 'UN', [], '0301000'],
            ['Água Mineral', ['Indaiá', 'Crystal', 'Minalba', 'Bonafont'], ['Sem Gás 500ml' => 2.29, 'Com Gás 500ml' => 2.99, 'Sem Gás 1,5L' => 3.99], '22011000', 'UN', [], '0301200'],
            ['Água Mineral Galão', ['Indaiá', 'Crystal'], ['20L (refil)' => 13.00], '22011000', 'UN', [], '0301200'],
            ['Cerveja', ['Skol', 'Brahma', 'Heineken', 'Itaipava', 'Budweiser', 'Antarctica', 'Stella Artois', 'Amstel'], ['Lata 350ml' => 4.29, 'Lata 473ml' => 5.49, 'Long Neck 330ml' => 6.99, 'Garrafa 600ml' => 9.49], '22030000', 'UN', [], '0300100'],
            ['Suco', ['Del Valle', 'Maratá', 'Tial', 'Sufresh', 'Maguary'], ['Uva 1L' => 8.49, 'Laranja 1L' => 8.49, 'Pêssego 1L' => 8.49, 'Caju 1L' => 7.49, 'Maracujá 1L' => 7.99, 'Caixinha Uva 200ml' => 2.99], '20096100', 'UN'],
            ['Suco Concentrado', ['Maguary', 'Maratá', 'Bela Ischia'], ['Caju 500ml' => 6.99, 'Goiaba 500ml' => 6.99, 'Maracujá 500ml' => 9.99], '20099000', 'UN'],
            ['Energético', ['Red Bull', 'Monster', 'TNT', 'Fusion'], ['Lata 250ml' => 9.99, 'Lata 473ml' => 11.99], '22029900', 'UN'],
            ['Isotônico', ['Gatorade', 'Powerade'], ['Laranja 500ml' => 6.49, 'Limão 500ml' => 6.49, 'Uva 500ml' => 6.49], '22029900', 'UN'],
            ['Vinho Tinto', ['Pérgola', 'Casillero del Diablo', 'Santa Helena', 'Quinta do Morgado', 'Miolo'], ['Suave 750ml' => 24.90, 'Seco 750ml' => 39.90], '22042100', 'UN'],
            ['Cachaça', ['Ypióca', '51', 'Pitú', 'Volúpia', 'Matuta'], ['965ml' => 18.90, '600ml' => 13.90], '22089000', 'UN'],
            ['Vodka', ['Smirnoff', 'Absolut', 'Orloff'], ['998ml' => 39.90], '22086000', 'UN'],
        ]],
        'Açougue e Frios' => ['giro' => 12, 'estoque' => [10, 90], 'qtd' => [0.3, 3.0], 'icms' => '102', 'kg' => true, 'itens' => [
            ['Carne Bovina', ['Friboi', 'Swift', 'Maturatta'], ['Patinho kg' => 42.90, 'Alcatra kg' => 49.90, 'Picanha kg' => 79.90, 'Acém kg' => 32.90, 'Costela kg' => 29.90, 'Carne Moída kg' => 34.90, 'Coxão Mole kg' => 44.90, 'Fraldinha kg' => 46.90], '02013000', 'KG'],
            ['Frango', ['Sadia', 'Seara', 'Perdigão', 'Guaraves'], ['Inteiro kg' => 13.90, 'Peito kg' => 19.90, 'Coxa e Sobrecoxa kg' => 14.90, 'Filé de Peito kg' => 24.90, 'Asa kg' => 21.90], '02071200', 'KG'],
            ['Linguiça', ['Sadia', 'Seara', 'Perdigão', 'Aurora'], ['Toscana kg' => 22.90, 'Calabresa kg' => 26.90], '16010000', 'KG'],
            ['Queijo', ['Tirolez', 'Polenghi', 'Sertãozinho', 'Puro Sabor'], ['Mussarela kg' => 49.90, 'Coalho kg' => 54.90, 'Prato kg' => 56.90, 'Manteiga kg' => 64.90], '04069030', 'KG'],
            ['Presunto', ['Sadia', 'Seara', 'Perdigão', 'Aurora'], ['Cozido kg' => 34.90, 'Defumado kg' => 39.90], '16024100', 'KG'],
            ['Mortadela', ['Sadia', 'Seara', 'Perdigão'], ['Tradicional kg' => 19.90, 'Defumada kg' => 22.90], '16010000', 'KG'],
        ]],
        'Higiene e Beleza' => ['giro' => 9, 'estoque' => [12, 120], 'qtd' => [1, 4], 'icms' => '102', 'itens' => [
            ['Sabonete', ['Dove', 'Lux', 'Protex', 'Palmolive', 'Francis', 'Phebo'], ['Original 85g' => 3.49, 'Hidratante 85g' => 3.79, 'Antibacteriano 85g' => 4.29, 'Líquido 250ml' => 14.90], '34011190', 'UN'],
            ['Shampoo', ['Seda', 'Pantene', 'Elseve', 'Head & Shoulders', 'Dove', 'TRESemmé'], ['Hidratação 325ml' => 19.90, 'Cachos 325ml' => 19.90, 'Anticaspa 200ml' => 24.90, 'Liso Perfeito 400ml' => 22.90], '33051000', 'UN', [], '2002300'],
            ['Condicionador', ['Seda', 'Pantene', 'Elseve', 'Dove', 'TRESemmé'], ['Hidratação 325ml' => 21.90, 'Cachos 325ml' => 21.90], '33059000', 'UN'],
            ['Desodorante', ['Rexona', 'Dove', 'Nivea', 'Axe', 'Monange', 'Above'], ['Aerosol 150ml' => 16.90, 'Roll-on 50ml' => 11.90, 'Aerosol Men 150ml' => 17.90], '33072010', 'UN'],
            ['Creme Dental', ['Colgate', 'Sorriso', 'Oral-B', 'Close Up', 'Sensodyne'], ['Tradicional 90g' => 4.49, 'Branqueador 70g' => 7.99, 'Sensível 90g' => 19.90], '33061000', 'UN'],
            ['Escova Dental', ['Colgate', 'Oral-B', 'Condor'], ['Macia' => 6.99, 'Média' => 6.99, 'Kit com 3' => 14.90], '96032100', 'UN'],
            ['Papel Higiênico', ['Neve', 'Personal', 'Sublime', 'Mili', 'Fofinho'], ['Folha Dupla 4 rolos' => 7.99, 'Folha Dupla 12 rolos' => 22.90, 'Folha Simples 4 rolos' => 4.99], '48181000', 'PCT'],
            ['Fralda Descartável', ['Pampers', 'Huggies', 'MamyPoko', 'Turma da Mônica'], ['P 30un' => 49.90, 'M 28un' => 49.90, 'G 24un' => 49.90, 'XG 20un' => 49.90], '96190000', 'PCT'],
            ['Absorvente', ['Always', 'Intimus', 'Sempre Livre'], ['Com Abas 8un' => 6.99, 'Noturno 8un' => 9.49], '96190000', 'PCT'],
            ['Hidratante Corporal', ['Nivea', 'Dove', 'Monange', 'Paixão'], ['200ml' => 16.90, '400ml' => 26.90], '33049910', 'UN', [], '2000100'],
            ['Aparelho de Barbear', ['Gillette', 'Bic', 'Prestobarba'], ['Descartável 2un' => 9.90, 'Mach3' => 29.90], '82121010', 'UN'],
        ]],
        'Limpeza' => ['giro' => 9, 'estoque' => [12, 140], 'qtd' => [1, 4], 'icms' => '102', 'itens' => [
            ['Detergente', ['Ypê', 'Limpol', 'Minuano', 'Brilhante'], ['Neutro 500ml' => 2.79, 'Limão 500ml' => 2.79, 'Coco 500ml' => 2.79], '34022000', 'UN'],
            ['Sabão em Pó', ['Omo', 'Brilhante', 'Ariel', 'Tixan Ypê', 'Surf'], ['800g' => 14.90, '1,6kg' => 27.90], '34022000', 'CX'],
            ['Sabão Líquido', ['Omo', 'Ariel', 'Brilhante', 'Ypê'], ['3L' => 49.90, '1,5L' => 29.90], '34022000', 'UN'],
            ['Água Sanitária', ['Qboa', 'Ypê', 'Dragão', 'Super Globo'], ['1L' => 4.29, '2L' => 6.99], '28289011', 'UN'],
            ['Amaciante', ['Comfort', 'Downy', 'Ypê', 'Fofo'], ['Concentrado 500ml' => 12.90, 'Tradicional 2L' => 16.90], '38099190', 'UN'],
            ['Desinfetante', ['Pinho Sol', 'Veja', 'Ypê', 'Kalipto'], ['Lavanda 500ml' => 5.99, 'Eucalipto 2L' => 14.90], '38089419', 'UN'],
            ['Limpador Multiuso', ['Veja', 'Mr. Músculo', 'Ypê'], ['Original 500ml' => 6.99, 'Cozinha 500ml' => 9.99], '34029039', 'UN'],
            ['Esponja', ['Scotch-Brite', 'Bettanin', 'Assolan'], ['Dupla Face 3un' => 5.99, 'Lã de Aço 8un' => 3.49], '68053090', 'PCT'],
            ['Saco de Lixo', ['Dover Roll', 'Embalixo', 'Plastifort'], ['30L 50un' => 9.99, '100L 10un' => 11.99], '39232190', 'PCT'],
            ['Inseticida', ['SBP', 'Raid', 'Baygon'], ['Aerosol 300ml' => 17.90, 'Elétrico Refil' => 14.90], '38089119', 'UN'],
        ]],
        'Celulares e Acessórios' => ['giro' => 4, 'estoque' => [2, 20], 'qtd' => [1, 1], 'icms' => '102', 'itens' => [
            ['Smartphone Samsung Galaxy', [''], ['A06 64GB' => 699.00, 'A16 128GB' => 1099.00, 'A26 5G 128GB' => 1699.00, 'A36 5G 256GB' => 2199.00, 'A56 5G 256GB' => 2699.00, 'S24 FE 128GB' => 3299.00], '85171300', 'UN', [['Preto', 'Azul', 'Verde']], '2105300'],
            ['Smartphone Motorola Moto', [''], ['G05 128GB' => 799.00, 'G15 128GB' => 999.00, 'G35 5G 128GB' => 1199.00, 'G55 5G 256GB' => 1499.00, 'G75 5G 256GB' => 1899.00, 'Edge 50 Fusion 256GB' => 2299.00], '85171300', 'UN', [['Grafite', 'Azul', 'Verde']], '2105300'],
            ['Smartphone Xiaomi Redmi', [''], ['14C 128GB' => 899.00, 'Note 14 256GB' => 1499.00, 'Note 14 Pro 256GB' => 2199.00], '85171300', 'UN', [['Preto', 'Azul', 'Roxo']], '2105300'],
            ['iPhone', [''], ['13 128GB' => 3999.00, '14 128GB' => 4499.00, '15 128GB' => 5299.00, '16 128GB' => 6499.00], '85171300', 'UN', [['Preto', 'Branco', 'Azul']], '2105300'],
            ['Capa para Celular', ['Samsung', 'Motorola', 'Xiaomi', 'iPhone'], ['Silicone' => 29.90, 'Antiimpacto' => 39.90, 'Carteira' => 49.90], '42029200', 'UN'],
            ['Película de Vidro 3D', ['Samsung', 'Motorola', 'Xiaomi', 'iPhone'], ['Linha A' => 19.90, 'Linha G' => 19.90, 'Linha Premium' => 34.90], '70071900', 'UN'],
            ['Carregador', ['Samsung', 'Motorola', 'Xiaomi', 'Apple', 'i2GO', 'Geonav'], ['Turbo 25W USB-C' => 89.90, 'Turbo 20W USB-C' => 79.90, 'Veicular 2 USB' => 39.90], '85044010', 'UN'],
            ['Cabo USB-C', ['Samsung', 'Motorola', 'i2GO', 'Geonav', 'Baseus'], ['1m' => 29.90, '2m' => 39.90], '85444200', 'UN'],
            ['Cabo Lightning', ['Apple', 'i2GO', 'Geonav'], ['1m' => 39.90], '85444200', 'UN'],
            ['Fone de Ouvido', ['JBL', 'Samsung', 'Philips', 'i2GO', 'Xiaomi'], ['Com Fio P2' => 39.90, 'Bluetooth TWS' => 149.90, 'Headphone Bluetooth' => 249.90], '85183000', 'UN'],
            ['Caixa de Som Bluetooth', ['JBL', 'Philco', 'Mondial', 'Lenoxx'], ['Go 3' => 249.00, 'Flip 6' => 699.00, 'Partybox Portátil' => 1299.00], '85182200', 'UN'],
            ['Carregador Portátil', ['Xiaomi', 'Baseus', 'i2GO', 'Geonav'], ['10.000mAh' => 119.90, '20.000mAh' => 189.90], '85076000', 'UN'],
            ['Cartão de Memória', ['SanDisk', 'Kingston', 'Samsung'], ['64GB' => 49.90, '128GB' => 89.90], '85235110', 'UN'],
            ['Smartwatch', ['Xiaomi', 'Samsung', 'Amazfit'], ['Band 9' => 299.00, 'Galaxy Watch 7' => 1499.00, 'Bip 5' => 549.00], '85176277', 'UN'],
        ]],
        'Eletrodomésticos' => ['giro' => 3, 'estoque' => [2, 15], 'qtd' => [1, 1], 'icms' => '102', 'entrega' => true, 'itens' => [
            ['Liquidificador', ['Mondial', 'Philco', 'Arno', 'Britânia', 'Oster', 'Walita'], ['Turbo 900W' => 149.90, 'Power 1200W' => 219.90], '85094010', 'UN', [['110V', '220V']], '2104100'],
            ['Ventilador', ['Arno', 'Mondial', 'Britânia', 'Ventisol'], ['Mesa 30cm' => 129.90, 'Mesa 40cm' => 179.90, 'Coluna 40cm' => 239.90, 'Parede 50cm' => 289.90], '84145120', 'UN', [['110V', '220V']]],
            ['Ferro de Passar', ['Black+Decker', 'Philips Walita', 'Mondial', 'Arno'], ['A Vapor' => 129.90, 'A Seco' => 69.90], '85164000', 'UN', [['110V', '220V']]],
            ['Fritadeira Air Fryer', ['Mondial', 'Philco', 'Britânia', 'Electrolux', 'Arno'], ['4L' => 349.90, '5,5L' => 449.90], '85167920', 'UN', [['110V', '220V']]],
            ['Micro-ondas', ['Electrolux', 'Panasonic', 'Philco', 'Midea'], ['20L' => 549.00, '30L' => 749.00], '85165000', 'UN', [['110V', '220V']]],
            ['Geladeira', ['Consul', 'Brastemp', 'Electrolux', 'Midea'], ['Frost Free 340L' => 3299.00, 'Frost Free 450L' => 4299.00, 'Degelo 260L' => 2199.00], '84181000', 'UN', [['110V', '220V']], '2103200'],
            ['Fogão', ['Atlas', 'Electrolux', 'Consul', 'Mueller'], ['4 Bocas' => 899.00, '5 Bocas' => 1399.00], '73211100', 'UN'],
            ['Smart TV', ['Samsung', 'LG', 'TCL', 'Philco', 'AOC'], ['32" HD' => 1199.00, '43" Full HD' => 1699.00, '50" 4K' => 2299.00, '55" 4K' => 2799.00, '65" 4K' => 3999.00], '85287200', 'UN', [], '2106900'],
            ['Ar-Condicionado Split', ['Samsung', 'LG', 'Elgin', 'Gree', 'Midea'], ['9.000 BTUs' => 1999.00, '12.000 BTUs' => 2399.00, '18.000 BTUs' => 3499.00], '84151011', 'UN', [['220V']]],
            ['Notebook', ['Lenovo IdeaPad', 'Dell Inspiron', 'Acer Aspire', 'Samsung Galaxy Book', 'Positivo'], ['i3 8GB 256GB SSD' => 2599.00, 'i5 8GB 512GB SSD' => 3499.00, 'Ryzen 5 16GB 512GB SSD' => 3999.00], '84713012', 'UN', [], '2103700'],
            ['Panela Elétrica de Arroz', ['Mondial', 'Britânia', 'Philco'], ['5 Xícaras' => 159.90, '10 Xícaras' => 219.90], '85166000', 'UN', [['110V', '220V']]],
            ['Cafeteira Elétrica', ['Mondial', 'Britânia', 'Philco', 'Oster'], ['15 Xícaras' => 119.90, '30 Xícaras' => 169.90], '85167100', 'UN', [['110V', '220V']]],
        ]],
        'Utilidades Domésticas' => ['giro' => 3, 'estoque' => [6, 60], 'qtd' => [1, 3], 'icms' => '102', 'itens' => [
            ['Panela', ['Tramontina', 'Rochedo', 'Panelux', 'Brinox'], ['Antiaderente 20cm' => 79.90, 'Antiaderente 24cm' => 99.90, 'de Pressão 4,5L' => 149.90, 'Jogo 5 Peças' => 299.90], '73239300', 'UN'],
            ['Garrafa Térmica', ['Invicta', 'Termolar', 'Mor'], ['1L' => 49.90, '1,8L' => 69.90], '96170010', 'UN'],
            ['Pote Hermético', ['Sanremo', 'Electrolux', 'Plasútil'], ['Kit 3 peças' => 29.90, 'Kit 10 peças' => 69.90], '39241000', 'UN'],
            ['Jogo de Copos', ['Nadir', 'Cisper'], ['Americano 6un' => 24.90, 'Long Drink 6un' => 34.90], '70133700', 'UN'],
            ['Faqueiro', ['Tramontina'], ['24 peças' => 89.90, '42 peças' => 159.90], '82152000', 'UN'],
            ['Toalha de Banho', ['Santista', 'Buddemeyer', 'Teka', 'Döhler'], ['Lisa' => 39.90, 'Gigante' => 59.90], '63026000', 'UN', [['Branca', 'Azul', 'Bege', 'Cinza']]],
            ['Jogo de Lençol', ['Santista', 'Teka', 'Altenburg'], ['Casal 4 peças' => 99.90, 'Solteiro 3 peças' => 69.90], '63022100', 'UN', [['Branco', 'Azul', 'Bege']]],
        ]],
        'Ferragens e Construção' => ['giro' => 4, 'estoque' => [10, 200], 'qtd' => [1, 10], 'icms' => '102', 'itens' => [
            ['Parafuso Phillips', ['Ciser', 'Belenus'], ['3,5x25mm 100un' => 12.90, '4,0x30mm 100un' => 14.90, '4,5x40mm 100un' => 17.90, '5,0x50mm 50un' => 15.90], '73181400', 'PCT'],
            ['Bucha de Nylon', ['Fischer', 'Tecbril'], ['6mm 100un' => 9.90, '8mm 100un' => 12.90, '10mm 50un' => 12.90], '39269090', 'PCT'],
            ['Lâmpada LED', ['Philips', 'Osram', 'Elgin', 'Avant', 'Golden'], ['9W' => 9.90, '12W' => 12.90, '15W' => 16.90, 'Tubular 18W' => 24.90], '85395200', 'UN', [['Branca Fria', 'Branca Quente']]],
            ['Tomada', ['Pial Legrand', 'Tramontina', 'Fame'], ['2P+T 10A' => 14.90, '2P+T 20A' => 17.90, 'Dupla 10A' => 24.90], '85366910', 'UN'],
            ['Extensão Elétrica', ['Fortrek', 'Ilumi', 'Tramontina'], ['3 tomadas 3m' => 29.90, '5 tomadas 5m' => 49.90], '85444900', 'UN'],
            ['Fita Isolante', ['3M', 'Tigre', 'Prysmian'], ['10m' => 7.90, '20m' => 12.90], '39191000', 'UN'],
            ['Cabo Flexível', ['Sil', 'Cobrecom', 'Prysmian'], ['1,5mm 100m' => 189.90, '2,5mm 100m' => 289.90, '4,0mm 100m' => 459.90], '85444900', 'UN', [['Azul', 'Preto', 'Vermelho', 'Verde']]],
            ['Tinta Acrílica', ['Suvinil', 'Coral', 'Sherwin-Williams', 'Iquine'], ['Fosca 3,6L' => 119.90, 'Fosca 18L' => 449.90, 'Semibrilho 3,6L' => 139.90], '32091010', 'UN', [['Branco Neve', 'Gelo', 'Areia', 'Cinza Platina', 'Palha']]],
            ['Cimento', ['Votoran', 'Nassau', 'Elizabeth', 'Poty'], ['CP II 50kg' => 39.90], '25232910', 'SC'],
            ['Argamassa', ['Quartzolit', 'Votomassa', 'Elizabeth'], ['AC I 20kg' => 19.90, 'AC III 20kg' => 39.90], '32149000', 'SC'],
            ['Furadeira de Impacto', ['Bosch', 'Black+Decker', 'Makita', 'Vonder'], ['550W' => 249.90, '700W' => 359.90], '84672100', 'UN', [['110V', '220V']]],
            ['Martelo', ['Tramontina', 'Vonder', 'Stanley'], ['Unha 27mm' => 39.90, 'Borracha 60mm' => 29.90], '82052000', 'UN'],
            ['Chave de Fenda', ['Tramontina', 'Vonder', 'Gedore'], ['1/8x4' => 9.90, '3/16x6' => 12.90, 'Phillips 1/4x6' => 14.90], '82054000', 'UN'],
            ['Trena', ['Starrett', 'Vonder', 'Stanley'], ['3m' => 19.90, '5m' => 29.90, '8m' => 44.90], '90178010', 'UN'],
        ]],
        'Vestuário e Calçados' => ['giro' => 4, 'estoque' => [3, 30], 'qtd' => [1, 2], 'icms' => '102', 'itens' => [
            ['Camiseta Básica', ['Hering', 'Malwee', 'Reserva', 'Basicamente'], ['Algodão' => 49.90, 'Gola V' => 54.90], '61091000', 'UN', [['P', 'M', 'G', 'GG'], ['Branca', 'Preta', 'Azul Marinho', 'Cinza Mescla']]],
            ['Calça Jeans', ['Levi\'s', 'Hering', 'Colcci', 'Malwee'], ['Masculina Slim' => 159.90, 'Feminina Skinny' => 149.90], '62034200', 'UN', [['36', '38', '40', '42', '44', '46']]],
            ['Bermuda', ['Hering', 'Malwee', 'Cyclone'], ['Sarja Masculina' => 89.90, 'Tactel Masculina' => 59.90], '62034300', 'UN', [['38', '40', '42', '44', '46']]],
            ['Chinelo', ['Havaianas', 'Rider', 'Ipanema', 'Kenner'], ['Tradicional' => 39.90, 'Slim' => 44.90, 'Top Max' => 54.90], '64022000', 'UN', [['33/34', '35/36', '37/38', '39/40', '41/42', '43/44'], ['Preto', 'Azul', 'Branco']]],
            ['Tênis', ['Olympikus', 'Mizuno', 'Nike', 'Adidas', 'Fila'], ['Corrida' => 299.90, 'Casual' => 249.90], '64041100', 'UN', [['37', '38', '39', '40', '41', '42', '43'], ['Preto', 'Branco']]],
            ['Meia', ['Lupo', 'Puket', 'Trifil'], ['Cano Médio Kit 3' => 39.90, 'Soquete Kit 3' => 29.90], '61159500', 'PCT', [['Branca', 'Preta']]],
        ]],
    ];

    /** Peças da assistência técnica: [peça, [modelos], preço base, NCM]. */
    public const PECAS = [
        ['Tela Frontal', ['Samsung Galaxy A06', 'Samsung Galaxy A16', 'Samsung Galaxy A26', 'Samsung Galaxy A36', 'Moto G05', 'Moto G15', 'Moto G35', 'Moto G55', 'Redmi 14C', 'Redmi Note 14', 'iPhone 11', 'iPhone 12', 'iPhone 13', 'iPhone 14'], 189.00, '85177900'],
        ['Bateria', ['Samsung Galaxy A06', 'Samsung Galaxy A16', 'Samsung Galaxy A26', 'Moto G05', 'Moto G15', 'Moto G35', 'Redmi 14C', 'Redmi Note 14', 'iPhone 11', 'iPhone 12', 'iPhone 13'], 89.00, '85076000'],
        ['Conector de Carga', ['Samsung Galaxy A06', 'Samsung Galaxy A16', 'Samsung Galaxy A26', 'Moto G05', 'Moto G15', 'Moto G35', 'Redmi 14C', 'Redmi Note 14', 'iPhone 11', 'iPhone 12', 'iPhone 13'], 49.00, '85177900'],
        ['Alto-falante', ['Samsung Galaxy A16', 'Moto G15', 'Redmi 14C', 'iPhone 12', 'iPhone 13'], 39.00, '85182900'],
        ['Kit Barras de LED TV', ['32"', '43"', '50"', '55"'], 129.00, '85299020'],
        ['Placa Fonte TV', ['Samsung 43"', 'LG 50"', 'TCL 55"', 'Philco 32"'], 249.00, '85299020'],
        ['Tela de Notebook 15,6" LED', ['Slim 30 pinos', 'Slim 40 pinos'], 399.00, '84733041'],
        ['Conector HDMI', ['PlayStation 4', 'PlayStation 5', 'Xbox Series S'], 59.00, '85366990'],
        ['SSD', ['240GB SATA', '480GB SATA', '512GB NVMe'], 179.00, '84717012'],
    ];

    /** Serviços (produto tipo 2): nome => preço. */
    public const SERVICOS = [
        'Mão de obra - troca de tela de celular' => 80.00,
        'Mão de obra - troca de bateria de celular' => 50.00,
        'Mão de obra - troca de conector de carga' => 60.00,
        'Mão de obra - troca de alto-falante' => 50.00,
        'Reparo em placa de celular' => 180.00,
        'Limpeza química (oxidação)' => 120.00,
        'Formatação com backup' => 90.00,
        'Atualização de software' => 50.00,
        'Reparo em TV (mão de obra)' => 150.00,
        'Troca de tela de notebook (mão de obra)' => 120.00,
        'Formatação de notebook com backup' => 120.00,
        'Instalação de SSD e clonagem' => 100.00,
        'Limpeza interna e troca de pasta térmica' => 110.00,
        'Troca de conector HDMI (mão de obra)' => 150.00,
        'Instalação de TV na parede' => 120.00,
        'Instalação de ar-condicionado 9.000 a 12.000 BTUs' => 450.00,
        'Instalação de ar-condicionado 18.000 BTUs' => 550.00,
        'Higienização de ar-condicionado' => 180.00,
        'Montagem de móveis (hora)' => 70.00,
        'Instalação elétrica (hora técnica)' => 80.00,
        'Diagnóstico técnico' => 40.00,
        'Entrega e instalação de eletrodoméstico' => 60.00,
    ];

    /**
     * Ordens de serviço: tipo de equipamento => [equipamentos [nome, marca, modelo, peça], defeitos].
     * Defeito: [relato, diagnóstico, serviço executado, serviço cobrado, peça (prefixo) ou null].
     */
    public const EQUIPAMENTOS = [
        'celular' => [
            'peso' => 70,
            'aparelhos' => [
                ['Smartphone', 'Samsung', 'Galaxy A06'], ['Smartphone', 'Samsung', 'Galaxy A16'], ['Smartphone', 'Samsung', 'Galaxy A26'],
                ['Smartphone', 'Motorola', 'Moto G05'], ['Smartphone', 'Motorola', 'Moto G15'], ['Smartphone', 'Motorola', 'Moto G35'],
                ['Smartphone', 'Xiaomi', 'Redmi 14C'], ['Smartphone', 'Xiaomi', 'Redmi Note 14'],
                ['iPhone', 'Apple', 'iPhone 11'], ['iPhone', 'Apple', 'iPhone 12'], ['iPhone', 'Apple', 'iPhone 13'],
            ],
            'acessorios' => ['Sem acessórios', 'Capa', 'Capa e película', 'Carregador', 'Chip da operadora'],
            'defeitos' => [
                ['Tela quebrada após queda, touch falhando', 'Display e touch danificados.', 'Substituição do conjunto de tela e testes de touch.', 'Mão de obra - troca de tela de celular', 'Tela Frontal'],
                ['Tela quebrada, sem imagem', 'Display sem imagem por impacto.', 'Troca do frontal completo.', 'Mão de obra - troca de tela de celular', 'Tela Frontal'],
                ['Não carrega ou só carrega em certa posição', 'Conector de carga com mau contato.', 'Troca do conector de carga.', 'Mão de obra - troca de conector de carga', 'Conector de Carga'],
                ['Bateria descarregando muito rápido', 'Bateria com capacidade abaixo de 70%.', 'Troca da bateria e calibração.', 'Mão de obra - troca de bateria de celular', 'Bateria'],
                ['Celular desligando sozinho', 'Bateria estufada.', 'Troca da bateria.', 'Mão de obra - troca de bateria de celular', 'Bateria'],
                ['Sem som nas chamadas e vídeos', 'Alto-falante queimado.', 'Troca do alto-falante.', 'Mão de obra - troca de alto-falante', 'Alto-falante'],
                ['Aparelho lento e travando', 'Sistema corrompido e armazenamento cheio.', 'Backup, formatação e restauração dos dados.', 'Formatação com backup', null],
                ['Caiu na água e não liga', 'Oxidação na placa principal.', 'Limpeza química e reparo de trilhas.', 'Limpeza química (oxidação)', null],
                ['Não liga', 'Curto na linha de alimentação da placa.', 'Reparo em placa com troca de componentes.', 'Reparo em placa de celular', null],
            ],
        ],
        'tv' => [
            'peso' => 12,
            'aparelhos' => [['Smart TV', 'Samsung', '43" UN43T5300'], ['Smart TV', 'LG', '50" 50UR8750'], ['Smart TV', 'TCL', '55" 55P755'], ['Smart TV', 'Philco', '32" PTV32G70']],
            'acessorios' => ['Controle remoto', 'Sem acessórios', 'Controle remoto e base'],
            'defeitos' => [
                ['Tem som mas não tem imagem', 'Barras de LED queimadas.', 'Troca do kit de barras de LED.', 'Reparo em TV (mão de obra)', 'Kit Barras de LED TV'],
                ['Não liga, led de standby apagado', 'Placa fonte em curto.', 'Troca da placa fonte.', 'Reparo em TV (mão de obra)', 'Placa Fonte TV'],
                ['Imagem escura', 'Barras de LED com falha.', 'Substituição das barras de LED.', 'Reparo em TV (mão de obra)', 'Kit Barras de LED TV'],
            ],
        ],
        'notebook' => [
            'peso' => 12,
            'aparelhos' => [['Notebook', 'Lenovo', 'IdeaPad 3'], ['Notebook', 'Dell', 'Inspiron 15 3520'], ['Notebook', 'Acer', 'Aspire 5'], ['Notebook', 'Samsung', 'Galaxy Book 2'], ['Notebook', 'Positivo', 'Vision C14']],
            'acessorios' => ['Carregador', 'Sem acessórios', 'Carregador e mochila'],
            'defeitos' => [
                ['Muito lento para ligar', 'HD mecânico com setores danificados.', 'Troca por SSD e clonagem do sistema.', 'Instalação de SSD e clonagem', 'SSD'],
                ['Tela trincada', 'Painel LCD danificado.', 'Troca da tela.', 'Troca de tela de notebook (mão de obra)', 'Tela de Notebook 15,6" LED'],
                ['Esquentando e desligando', 'Cooler sujo e pasta térmica seca.', 'Limpeza interna e troca da pasta térmica.', 'Limpeza interna e troca de pasta térmica', null],
                ['Com vírus e janelas abrindo sozinhas', 'Sistema infectado.', 'Backup e reinstalação do sistema.', 'Formatação de notebook com backup', null],
            ],
        ],
        'videogame' => [
            'peso' => 6,
            'aparelhos' => [['Console', 'Sony', 'PlayStation 4'], ['Console', 'Sony', 'PlayStation 5'], ['Console', 'Microsoft', 'Xbox Series S']],
            'acessorios' => ['Sem acessórios', 'Controle', 'Cabo HDMI e fonte'],
            'defeitos' => [
                ['Sem sinal de vídeo na TV', 'Conector HDMI danificado.', 'Troca do conector HDMI.', 'Troca de conector HDMI (mão de obra)', 'Conector HDMI'],
                ['Barulho alto e superaquecendo', 'Poeira acumulada no cooler.', 'Limpeza interna e troca da pasta térmica.', 'Limpeza interna e troca de pasta térmica', null],
            ],
        ],
    ];

    /** Despesas fixas mensais: [descrição, categoria, fornecedor, dia de vencimento, valor mínimo, valor máximo]. */
    public const DESPESAS_FIXAS = [
        ['Aluguel do ponto comercial', 'Aluguel', 'Tambaú Imóveis e Administração Ltda', 5, 6500, 6500],
        ['Energia elétrica', 'Energia elétrica', 'Energisa Paraíba Distribuidora de Energia S/A', 10, 2600, 4300],
        ['Água e esgoto', 'Água e esgoto', 'Companhia de Água e Esgotos da Paraíba', 12, 260, 430],
        ['Internet fibra 600 Mega', 'Telefone e internet', 'Brisanet Telecomunicações S/A', 15, 199.90, 199.90],
        ['Telefone fixo e móvel corporativo', 'Telefone e internet', 'Claro S/A', 15, 149.90, 189.90],
        ['Honorários contábeis', 'Serviços contábeis', 'Contábil Paraíba Assessoria Ltda', 10, 1250, 1250],
        ['Mensalidade do sistema de gestão', 'Sistemas e software', 'Softcom Tecnologia Ltda', 10, 189.90, 189.90],
        ['Taxas da maquininha de cartão', 'Taxas de cartão', 'Rede Adquirência e Pagamentos S/A', 1, 2100, 3900],
        ['Tarifa do pacote de serviços bancários', 'Tarifas bancárias', 'Banco do Brasil S/A', 3, 79.90, 79.90],
        ['Anúncios no Instagram e Google', 'Marketing', 'Agência Litoral Digital Ltda', 20, 300, 950],
        ['Limpeza e conservação', 'Manutenção', 'Limpa Mais Serviços Ltda', 25, 1100, 1100],
        ['Material de escritório e embalagens', 'Material de escritório', 'Papelaria Central Ltda', 18, 180, 620],
        ['Monitoramento e alarme', 'Manutenção', 'Segurança Total Monitoramento Ltda', 15, 159.90, 159.90],
    ];

    public const CATEGORIAS_RECEITA = ['Vendas', 'Serviços', 'Aluguéis recebidos', 'Contratos de manutenção', 'Outras receitas'];

    public const CATEGORIAS_DESPESA = [
        'Compras / Estoque', 'Comissões', 'Aluguel', 'Energia elétrica', 'Água e esgoto', 'Telefone e internet', 'Folha de pagamento',
        'Encargos trabalhistas', 'Impostos', 'Serviços contábeis', 'Tarifas bancárias', 'Taxas de cartão', 'Marketing', 'Manutenção',
        'Material de escritório', 'Sistemas e software', 'Fretes',
    ];

    public const RESULTADOS_COBRANCA = [
        'telefone' => ['Cliente prometeu pagar até sexta-feira.', 'Não atendeu, deixar recado.', 'Pediu para ligar na próxima semana.', 'Informou que pagará no dia 10.'],
        'whatsapp' => ['Mensagem enviada com o boleto atualizado.', 'Cliente visualizou e não respondeu.', 'Cliente pediu o Pix para pagamento.', 'Combinado pagamento em duas vezes.'],
        'email' => ['Boleto atualizado enviado por e-mail.', 'Lembrete de vencimento enviado.'],
        'presencial' => ['Cliente esteve na loja e pediu prazo.', 'Cliente vai passar no sábado para acertar.'],
    ];
}
