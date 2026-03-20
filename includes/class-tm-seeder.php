    <?php

    if (! defined('ABSPATH')) {
        exit;
    }

    class TM_Seeder
    {
        protected static function categories_data(): array
        {
            return json_decode(<<<'JSON'
[
  {
    "name": "Maquinarias y Vehículos",
    "slug": "maquinarias-y-vehiculos",
    "subcategories": [
      "Tractores",
      "Camionetas",
      "Camiones",
      "Implementos",
      "Cosechadoras",
      "Tolvas",
      "Pulverizadores",
      "Rastras",
      "Sembradoras",
      "Otros"
    ]
  },
  {
    "name": "Animales / Ganadería",
    "slug": "animales-ganaderia",
    "subcategories": [
      "Bovinos",
      "Ovinos",
      "Caprinos",
      "Equinos",
      "Aves",
      "Perros",
      "Otros"
    ]
  },
  {
    "name": "Propiedades",
    "slug": "propiedades",
    "subcategories": [
      "Parcelas",
      "Campos",
      "Sitios",
      "Bodegas",
      "Casas",
      "Otros"
    ]
  },
  {
    "name": "Apicultura",
    "slug": "apicultura",
    "subcategories": [
      "Abejas",
      "Colmenas",
      "Miel",
      "Equipos",
      "Accesorios",
      "Otros"
    ]
  },
  {
    "name": "Producción Agrícola",
    "slug": "produccion-agricola",
    "subcategories": [
      "Frutas",
      "Hortalizas",
      "Cereales",
      "Legumbres",
      "Semillas",
      "Otros"
    ]
  },
  {
    "name": "Deporte / Rodeo",
    "slug": "deporte-rodeo",
    "subcategories": [
      "Monturas",
      "Ropa",
      "Accesorios",
      "Caballos",
      "Otros"
    ]
  },
  {
    "name": "Insumos Agrícolas",
    "slug": "insumos-agricolas",
    "subcategories": [
      "Fertilizantes",
      "Agroquímicos",
      "Sustratos",
      "Herramientas",
      "Repuestos",
      "Otros"
    ]
  },
  {
    "name": "Mano de Obra / Profesionales",
    "slug": "mano-de-obra-profesionales",
    "subcategories": [
      "Operadores",
      "Técnicos",
      "Administrativos",
      "Asesorías",
      "Servicios",
      "Otros"
    ]
  },
  {
    "name": "Vivero / Flores / Ornamentales",
    "slug": "vivero-flores-ornamentales",
    "subcategories": [
      "Plantas",
      "Árboles",
      "Flores",
      "Césped",
      "Macetas",
      "Otros"
    ]
  },
  {
    "name": "Vitivinicultura",
    "slug": "vitivinicultura",
    "subcategories": [
      "Uvas",
      "Barricas",
      "Insumos",
      "Equipos",
      "Otros"
    ]
  },
  {
    "name": "Mundo Orgánico y Gourmet",
    "slug": "mundo-organico-y-gourmet",
    "subcategories": [
      "Orgánico",
      "Gourmet",
      "Procesados",
      "Artesanales",
      "Otros"
    ]
  },
  {
    "name": "Riego y Tecnología",
    "slug": "riego-y-tecnologia",
    "subcategories": [
      "Riego por goteo",
      "Aspersión",
      "Bombas",
      "Sensores",
      "Automatización",
      "Otros"
    ]
  }
]
JSON, true) ?: array();
        }

        protected static function regions_data(): array
        {
            return json_decode(<<<'JSON'
[
  {
    "name": "Arica y Parinacota",
    "slug": "arica-y-parinacota",
    "code": "AP",
    "communes": [
      "Arica",
      "Camarones",
      "Putre",
      "General Lagos"
    ]
  },
  {
    "name": "Tarapacá",
    "slug": "tarapaca",
    "code": "TA",
    "communes": [
      "Iquique",
      "Alto Hospicio",
      "Camiña",
      "Colchane",
      "Huara",
      "Pica",
      "Pozo Almonte"
    ]
  },
  {
    "name": "Antofagasta",
    "slug": "antofagasta",
    "code": "AN",
    "communes": [
      "Antofagasta",
      "Mejillones",
      "Sierra Gorda",
      "Taltal",
      "Calama",
      "María Elena",
      "Ollagüe",
      "San Pedro de Atacama",
      "Tocopilla"
    ]
  },
  {
    "name": "Atacama",
    "slug": "atacama",
    "code": "AT",
    "communes": [
      "Copiapó",
      "Caldera",
      "Tierra Amarilla",
      "Chañaral",
      "Diego de Almagro",
      "Vallenar",
      "Alto del Carmen",
      "Freirina",
      "Huasco"
    ]
  },
  {
    "name": "Coquimbo",
    "slug": "coquimbo",
    "code": "CO",
    "communes": [
      "La Serena",
      "Coquimbo",
      "Andacollo",
      "La Higuera",
      "Paihuano",
      "Vicuña",
      "Illapel",
      "Canela",
      "Los Vilos",
      "Salamanca",
      "Ovalle",
      "Combarbalá",
      "Monte Patria",
      "Punitaqui",
      "Río Hurtado"
    ]
  },
  {
    "name": "Valparaíso",
    "slug": "valparaiso",
    "code": "VS",
    "communes": [
      "Valparaíso",
      "Casablanca",
      "Concón",
      "Juan Fernández",
      "Puchuncaví",
      "Quintero",
      "Viña del Mar",
      "Isla de Pascua",
      "Los Andes",
      "Calle Larga",
      "Rinconada",
      "San Esteban",
      "La Ligua",
      "Cabildo",
      "Papudo",
      "Petorca",
      "Zapallar",
      "Quillota",
      "La Calera",
      "Hijuelas",
      "La Cruz",
      "Nogales",
      "San Antonio",
      "Algarrobo",
      "Cartagena",
      "El Quisco",
      "El Tabo",
      "Santo Domingo",
      "San Felipe",
      "Catemu",
      "Llaillay",
      "Panquehue",
      "Putaendo",
      "Santa María",
      "Quilpué",
      "Limache",
      "Olmué",
      "Villa Alemana"
    ]
  },
  {
    "name": "Metropolitana de Santiago",
    "slug": "metropolitana-de-santiago",
    "code": "RM",
    "communes": [
      "Santiago",
      "Cerrillos",
      "Cerro Navia",
      "Conchalí",
      "El Bosque",
      "Estación Central",
      "Huechuraba",
      "Independencia",
      "La Cisterna",
      "La Florida",
      "La Granja",
      "La Pintana",
      "La Reina",
      "Las Condes",
      "Lo Barnechea",
      "Lo Espejo",
      "Lo Prado",
      "Macul",
      "Maipú",
      "Ñuñoa",
      "Pedro Aguirre Cerda",
      "Peñalolén",
      "Providencia",
      "Pudahuel",
      "Quilicura",
      "Quinta Normal",
      "Recoleta",
      "Renca",
      "San Joaquín",
      "San Miguel",
      "San Ramón",
      "Vitacura",
      "Puente Alto",
      "Pirque",
      "San José de Maipo",
      "Colina",
      "Lampa",
      "Tiltil",
      "San Bernardo",
      "Buin",
      "Calera de Tango",
      "Paine",
      "Melipilla",
      "Alhué",
      "Curacaví",
      "María Pinto",
      "San Pedro",
      "Talagante",
      "El Monte",
      "Isla de Maipo",
      "Padre Hurtado",
      "Peñaflor"
    ]
  },
  {
    "name": "Libertador General Bernardo O’Higgins",
    "slug": "libertador-general-bernardo-ohiggins",
    "code": "LI",
    "communes": [
      "Rancagua",
      "Codegua",
      "Coinco",
      "Coltauco",
      "Doñihue",
      "Graneros",
      "Las Cabras",
      "Machalí",
      "Malloa",
      "Mostazal",
      "Olivar",
      "Peumo",
      "Pichidegua",
      "Quinta de Tilcoco",
      "Rengo",
      "Requínoa",
      "San Vicente de Tagua Tagua",
      "Pichilemu",
      "La Estrella",
      "Litueche",
      "Marchigüe",
      "Navidad",
      "Paredones",
      "San Fernando",
      "Chépica",
      "Chimbarongo",
      "Lolol",
      "Nancagua",
      "Palmilla",
      "Peralillo",
      "Placilla",
      "Pumanque",
      "Santa Cruz"
    ]
  },
  {
    "name": "Maule",
    "slug": "maule",
    "code": "ML",
    "communes": [
      "Talca",
      "Constitución",
      "Curepto",
      "Empedrado",
      "Maule",
      "Pelarco",
      "Pencahue",
      "Río Claro",
      "San Clemente",
      "San Rafael",
      "Curicó",
      "Hualañé",
      "Licantén",
      "Molina",
      "Rauco",
      "Romeral",
      "Sagrada Familia",
      "Teno",
      "Vichuquén",
      "Linares",
      "Colbún",
      "Longaví",
      "Parral",
      "Retiro",
      "San Javier",
      "Villa Alegre",
      "Yerbas Buenas",
      "Cauquenes",
      "Chanco",
      "Pelluhue"
    ]
  },
  {
    "name": "Ñuble",
    "slug": "nuble",
    "code": "NB",
    "communes": [
      "Chillán",
      "Bulnes",
      "Cobquecura",
      "Coelemu",
      "Coihueco",
      "Chillán Viejo",
      "El Carmen",
      "Ninhue",
      "Ñiquén",
      "Pemuco",
      "Pinto",
      "Portezuelo",
      "Quillón",
      "Quirihue",
      "Ránquil",
      "San Carlos",
      "San Fabián",
      "San Ignacio",
      "San Nicolás",
      "Trehuaco",
      "Yungay"
    ]
  },
  {
    "name": "Biobío",
    "slug": "biobio",
    "code": "BI",
    "communes": [
      "Concepción",
      "Coronel",
      "Chiguayante",
      "Florida",
      "Hualqui",
      "Lota",
      "Penco",
      "San Pedro de la Paz",
      "Santa Juana",
      "Talcahuano",
      "Tomé",
      "Hualpén",
      "Lebu",
      "Arauco",
      "Cañete",
      "Contulmo",
      "Curanilahue",
      "Los Álamos",
      "Tirúa",
      "Los Ángeles",
      "Antuco",
      "Cabrero",
      "Laja",
      "Mulchén",
      "Nacimiento",
      "Negrete",
      "Quilaco",
      "Quilleco",
      "San Rosendo",
      "Santa Bárbara",
      "Tucapel",
      "Yumbel",
      "Alto Biobío"
    ]
  },
  {
    "name": "La Araucanía",
    "slug": "la-araucania",
    "code": "AR",
    "communes": [
      "Temuco",
      "Carahue",
      "Cunco",
      "Curarrehue",
      "Freire",
      "Galvarino",
      "Gorbea",
      "Lautaro",
      "Loncoche",
      "Melipeuco",
      "Nueva Imperial",
      "Padre Las Casas",
      "Perquenco",
      "Pitrufquén",
      "Pucón",
      "Saavedra",
      "Teodoro Schmidt",
      "Toltén",
      "Vilcún",
      "Villarrica",
      "Cholchol",
      "Angol",
      "Collipulli",
      "Curacautín",
      "Ercilla",
      "Lonquimay",
      "Los Sauces",
      "Lumaco",
      "Purén",
      "Renaico",
      "Traiguén",
      "Victoria"
    ]
  },
  {
    "name": "Los Ríos",
    "slug": "los-rios",
    "code": "LR",
    "communes": [
      "Valdivia",
      "Corral",
      "Lanco",
      "Los Lagos",
      "Máfil",
      "Mariquina",
      "Paillaco",
      "Panguipulli",
      "La Unión",
      "Futrono",
      "Lago Ranco",
      "Río Bueno"
    ]
  },
  {
    "name": "Los Lagos",
    "slug": "los-lagos",
    "code": "LL",
    "communes": [
      "Puerto Montt",
      "Calbuco",
      "Cochamó",
      "Fresia",
      "Frutillar",
      "Los Muermos",
      "Llanquihue",
      "Maullín",
      "Puerto Varas",
      "Castro",
      "Ancud",
      "Chonchi",
      "Curaco de Vélez",
      "Dalcahue",
      "Puqueldón",
      "Queilén",
      "Quellón",
      "Quemchi",
      "Quinchao",
      "Osorno",
      "Puerto Octay",
      "Purranque",
      "Puyehue",
      "Río Negro",
      "San Juan de la Costa",
      "San Pablo",
      "Chaitén",
      "Futaleufú",
      "Hualaihué",
      "Palena"
    ]
  },
  {
    "name": "Aysén del General Carlos Ibáñez del Campo",
    "slug": "aysen",
    "code": "AI",
    "communes": [
      "Coyhaique",
      "Lago Verde",
      "Aysén",
      "Cisnes",
      "Guaitecas",
      "Cochrane",
      "O’Higgins",
      "Tortel",
      "Chile Chico",
      "Río Ibáñez"
    ]
  },
  {
    "name": "Magallanes y de la Antártica Chilena",
    "slug": "magallanes-y-de-la-antartica-chilena",
    "code": "MA",
    "communes": [
      "Punta Arenas",
      "Laguna Blanca",
      "Río Verde",
      "San Gregorio",
      "Cabo de Hornos",
      "Antártica",
      "Porvenir",
      "Primavera",
      "Timaukel",
      "Puerto Natales",
      "Torres del Paine"
    ]
  }
]
JSON, true) ?: array();
        }

        protected static function dynamic_fields_data(): array
        {
            return json_decode(<<<'JSON'
[
  {
    "category_slug": "maquinarias-y-vehiculos",
    "field_key": "brand",
    "field_label": "Marca",
    "field_type": "select",
    "field_options": [
      "Massey Ferguson",
      "Valtra",
      "Case IH",
      "John Deere",
      "Toyota",
      "Chevrolet",
      "Nissan",
      "Otro"
    ],
    "is_required": 1,
    "sort_order": 10
  },
  {
    "category_slug": "maquinarias-y-vehiculos",
    "field_key": "year",
    "field_label": "Año",
    "field_type": "number",
    "field_options": [],
    "is_required": 0,
    "sort_order": 20
  },
  {
    "category_slug": "animales-ganaderia",
    "field_key": "breed",
    "field_label": "Raza",
    "field_type": "text",
    "field_options": [],
    "is_required": 0,
    "sort_order": 10
  },
  {
    "category_slug": "propiedades",
    "field_key": "surface",
    "field_label": "Superficie",
    "field_type": "text",
    "field_options": [],
    "is_required": 0,
    "sort_order": 10
  },
  {
    "category_slug": "vivero-flores-ornamentales",
    "field_key": "variety",
    "field_label": "Variedad",
    "field_type": "text",
    "field_options": [],
    "is_required": 0,
    "sort_order": 10
  }
]
JSON, true) ?: array();
        }

        public static function seed_all(): void
        {
            self::seed_conditions();
            self::seed_categories_and_subcategories();
            self::seed_regions_and_communes();
            self::seed_dynamic_fields();
        }

        public static function seed_conditions(): void
        {
            foreach (array('Nuevo', 'Usado') as $condition) {
                if (! term_exists($condition, 'tm_condition')) {
                    wp_insert_term($condition, 'tm_condition');
                }
            }
        }

        public static function seed_categories_and_subcategories(): void
        {
            foreach (self::categories_data() as $category) {
                $result = term_exists($category['slug'], 'tm_category');
                if (! $result) {
                    $result = wp_insert_term($category['name'], 'tm_category', array('slug' => $category['slug']));
                } else {
                    $term_id = is_array($result) ? (int) $result['term_id'] : (int) $result;
                    wp_update_term($term_id, 'tm_category', array('name' => $category['name'], 'slug' => $category['slug']));
                }

                $category_term_id = is_wp_error($result)
                    ? 0
                    : (is_array($result) ? (int) $result['term_id'] : (int) $result);

                if (! $category_term_id) {
                    $term = get_term_by('slug', $category['slug'], 'tm_category');
                    $category_term_id = $term ? (int) $term->term_id : 0;
                }

                foreach ($category['subcategories'] as $sub_name) {
                    $sub_slug = sanitize_title($category['slug'] . '-' . $sub_name);
                    $sub      = term_exists($sub_slug, 'tm_subcategory');
                    if (! $sub) {
                        $sub = wp_insert_term($sub_name, 'tm_subcategory', array('slug' => $sub_slug));
                    } else {
                        $sub_term_id = is_array($sub) ? (int) $sub['term_id'] : (int) $sub;
                        wp_update_term($sub_term_id, 'tm_subcategory', array('name' => $sub_name, 'slug' => $sub_slug));
                    }

                    $sub_term_id = is_wp_error($sub)
                        ? 0
                        : (is_array($sub) ? (int) $sub['term_id'] : (int) $sub);

                    if (! $sub_term_id) {
                        $term = get_term_by('slug', $sub_slug, 'tm_subcategory');
                        $sub_term_id = $term ? (int) $term->term_id : 0;
                    }

                    if ($sub_term_id && $category_term_id) {
                        update_term_meta($sub_term_id, 'tm_parent_category_id', $category_term_id);
                        update_term_meta($sub_term_id, 'tm_parent_category_slug', $category['slug']);
                    }
                }
            }
        }

        public static function seed_regions_and_communes(): void
        {
            foreach (self::regions_data() as $index => $region) {
                $term = term_exists($region['slug'], 'tm_region');
                if (! $term) {
                    $term = wp_insert_term($region['name'], 'tm_region', array('slug' => $region['slug']));
                } else {
                    $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                    wp_update_term($term_id, 'tm_region', array('name' => $region['name'], 'slug' => $region['slug']));
                }

                $region_term_id = is_wp_error($term)
                    ? 0
                    : (is_array($term) ? (int) $term['term_id'] : (int) $term);

                if (! $region_term_id) {
                    $region_term = get_term_by('slug', $region['slug'], 'tm_region');
                    $region_term_id = $region_term ? (int) $region_term->term_id : 0;
                }

                if ($region_term_id) {
                    update_term_meta($region_term_id, 'tm_region_order', $index + 1);
                    update_term_meta($region_term_id, 'tm_region_code', $region['code']);
                }

                foreach ($region['communes'] as $commune) {
                    $commune_slug = sanitize_title($region['slug'] . '-' . $commune);
                    $commune_term = term_exists($commune_slug, 'tm_comuna');
                    if (! $commune_term) {
                        $commune_term = wp_insert_term($commune, 'tm_comuna', array('slug' => $commune_slug));
                    } else {
                        $commune_term_id = is_array($commune_term) ? (int) $commune_term['term_id'] : (int) $commune_term;
                        wp_update_term($commune_term_id, 'tm_comuna', array('name' => $commune, 'slug' => $commune_slug));
                    }

                    $commune_term_id = is_wp_error($commune_term)
                        ? 0
                        : (is_array($commune_term) ? (int) $commune_term['term_id'] : (int) $commune_term);

                    if (! $commune_term_id) {
                        $commune_obj = get_term_by('slug', $commune_slug, 'tm_comuna');
                        $commune_term_id = $commune_obj ? (int) $commune_obj->term_id : 0;
                    }

                    if ($commune_term_id && $region_term_id) {
                        update_term_meta($commune_term_id, 'tm_region_id', $region_term_id);
                        update_term_meta($commune_term_id, 'tm_region_slug', $region['slug']);
                    }
                }
            }
        }

        public static function seed_dynamic_fields(): void
        {
            global $wpdb;
            $table = $wpdb->prefix . 'tm_category_fields';

            foreach (self::dynamic_fields_data() as $field) {
                $category = get_term_by('slug', $field['category_slug'], 'tm_category');
                if (! $category) {
                    continue;
                }

                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE category_term_id = %d AND subcategory_term_id = %d AND field_key = %s LIMIT 1",
                    (int) $category->term_id,
                    0,
                    $field['field_key']
                ));

                $data = array(
                    'category_term_id'   => (int) $category->term_id,
                    'subcategory_term_id'=> 0,
                    'field_key'          => sanitize_key($field['field_key']),
                    'field_label'        => sanitize_text_field($field['field_label']),
                    'field_type'         => sanitize_text_field($field['field_type']),
                    'field_options_json' => wp_json_encode($field['field_options'], JSON_UNESCAPED_UNICODE),
                    'is_required'        => ! empty($field['is_required']) ? 1 : 0,
                    'sort_order'         => (int) $field['sort_order'],
                    'is_active'          => 1,
                );

                if ($exists) {
                    $wpdb->update($table, $data, array('id' => (int) $exists));
                } else {
                    $wpdb->insert($table, $data);
                }
            }
        }
    }
