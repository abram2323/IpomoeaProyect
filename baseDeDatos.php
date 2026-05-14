<?php
    // 1. CONFIGURACIÓN DE LA CONEXIÓN (Actualizado)
    $host = "localhost";
    $user = "root"; 
    $pass = "";     
    $db   = "ipomoeaproject"; // Nombre de tu nueva base de datos

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($host, $user, $pass, $db);
        $conn->set_charset("utf8mb4");
    } catch (Exception $e) {
        die("Error crítico de conexión: " . $e->getMessage());
    }

    // 2. CONFIGURACIÓN DE PAGINACIÓN
    $resultados_por_pagina = 15;
    $pagina_actual = (isset($_GET['pagina']) && is_numeric($_GET['pagina'])) ? max(1, (int)$_GET['pagina']) : 1;
    $inicio_limite = ($pagina_actual - 1) * $resultados_por_pagina;

    $termino = isset($_GET['buscador']) ? trim($_GET['buscador']) : '';

    // 3. LÓGICA DE FILTROS DINÁMICOS
    $where_clause = " WHERE 1=1";
    $params = [];
    $types = "";

    // Filtro de búsqueda general ajustado a image_a28cf5.png
    if (!empty($termino)) {
        $q_like = "%" . $termino . "%";
        
        // Usamos los nombres exactos de la imagen
        $where_clause .= " AND (`Record Guid` LIKE ? 
                            OR Collectors LIKE ? 
                            OR CountryName LIKE ? 
                            OR CalcFullName LIKE ? 
                            OR GenusSection LIKE ?
                            OR CollectionYear LIKE ?)"; 
        
        array_push($params, $q_like, $q_like, $q_like, $q_like, $q_like, $q_like);
        $types .= "ssssss"; 
    }

    // 4. CONTEO TOTAL
    $stmt_conteo = $conn->prepare("SELECT COUNT(*) as total FROM datos_ipomoea $where_clause");
    if (!empty($params)) {
        $stmt_conteo->bind_param($types, ...$params);
    }
    $stmt_conteo->execute();
    $total_registros = $stmt_conteo->get_result()->fetch_assoc()['total'];
    $total_paginas = ceil($total_registros / $resultados_por_pagina);

    // 5. CONSULTA DE DATOS (Nombres exactos de la imagen image_a28cf5.png)
    // Nota: Como 'Record Guid' tiene un espacio, lo encerramos en comillas invertidas ``
    $sql_datos = "SELECT `Record Guid`, Collectors, CountryName, CalcFullName, GenusSection, CollectionYear
                FROM datos_ipomoea 
                $where_clause 
                LIMIT ?, ?";

    $stmt_datos = $conn->prepare($sql_datos);

    $types_datos = $types . "ii";
    $params_datos = $params;
    array_push($params_datos, $inicio_limite, $resultados_por_pagina);

    $stmt_datos->bind_param($types_datos, ...$params_datos);
    $stmt_datos->execute();
    $resultado = $stmt_datos->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="./css/baseDeDatos.css?v=<?php echo time(); ?>">
    <!-- 1. Primero el CSS de Leaflet (OBLIGATORIO) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.9.0/proj4.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mgrs@1.0.0/dist/mgrs.min.js"></script>

</head>
<body>
<header>
    <a href="portada.html">
        <p>The Ipomoea Proyect</p>
    </a>

    <div class="hamburger" id="hamburger">
        ☰
    </div>

    <div class="nav-links" id="navLinks">
        <a href="baseDeDatos.php">Base de datos</a>
        <a href="genero.html">El género</a>
        <a href="proyecto.html">El proyecto</a>
    </div>
</header>

    <div class="layout-principal">
        <button type="button" id="btn-toggle-filtros" class="btn-responsive-filtros">
            Filtros
        </button>

        <aside class="sidebar" id="sidebar-filtros">
            <form action="" method="GET" class="mi-formulario">
                    <div class="head">
                        <img src="img/flechaIzquierda.png" id="btn-cerrar-flecha" alt="Cerrar" style="cursor: pointer;">                        <label>Ocurrences</label>                       
                        <img src="img/basura.png" class="icono-basura" alt="Limpiar filtros" style="cursor: pointer;">
                    </div>

                    <div class="form-content">
                        <div class="form-group">
                            <input type="text" name="buscador" id="buscar" placeholder="Buscar en todos los campos"
                            value="<?php echo isset($_GET['buscador']) ? htmlspecialchars($_GET['buscador']) : ''; ?>">
                        </div>

                        <div class="dropdown">
                            <button type="button" class="dropbtn">Nombre científico<span>▼</span></button>
                            <div class="dropdown-content">
                                <input type="text" name="nombre_cientifico" placeholder="Buscar"
                                value="<?php echo isset($_GET['nombre_cientifico']) ? htmlspecialchars($_GET['nombre_cientifico']) : ''; ?>">
                            </div>
                        </div>

                        <div class="dropdown">
                            <button type="button" class="dropbtn">Coordenadas<span>▼</span></button>
                            <div class="dropdown-content">
                                <input type="text" name="coordenadas" placeholder="Buscar">
                            </div>
                        </div>

                        <div class="dropdown">
                            <button type="button" class="dropbtn">Localidad<span>▼</span></button>
                            <div class="dropdown-content">
                                <input type="text" name="localidad" placeholder="Buscar"
                                value="<?php echo isset($_GET['localidad']) ? htmlspecialchars($_GET['localidad']) : ''; ?>">
                            </div>
                        </div>

                        <div class="dropdown">
                            <button type="button" class="dropbtn">Región<span>▼</span></button>
                            <div class="dropdown-content">
                                <label><input type="checkbox" name="region[]" value="bioko_n">Bioko Norte</label>
                                <label><input type="checkbox" name="region[]" value="bioko_s">Bioko Sur</label>
                                <label><input type="checkbox" name="region[]" value="annobon">Annobón</label>
                                <label><input type="checkbox" name="region[]" value="litoral">Litoral</label>
                                <label><input type="checkbox" name="region[]" value="centro_s">Centro Sur</label>
                                <label><input type="checkbox" name="region[]" value="kie_ntem">Kié-Ntem</label>
                            </div>
                        </div>

                        <div class="dropdown">
                            <button type="button" class="dropbtn">Mes <span>▼</span></button>
                            <div class="dropdown-content">
                                <label><input type="checkbox" name="mes[]" value="1">Enero</label>
                                <label><input type="checkbox" name="mes[]" value="2">Febrero</label>
                                <label><input type="checkbox" name="mes[]" value="3">Marzo</label>
                                <label><input type="checkbox" name="mes[]" value="4">Abril</label>
                                <label><input type="checkbox" name="mes[]" value="5">Mayo</label>
                                <label><input type="checkbox" name="mes[]" value="6">Junio</label>
                                <label><input type="checkbox" name="mes[]" value="7">Julio</label>
                                <label><input type="checkbox" name="mes[]" value="8">Agosto</label>
                                <label><input type="checkbox" name="mes[]" value="9">Septiembre</label>
                                <label><input type="checkbox" name="mes[]" value="10">Octubre</label>
                                <label><input type="checkbox" name="mes[]" value="11">Noviembre</label>
                                <label><input type="checkbox" name="mes[]" value="12">Diciembre</label>
                            </div>
                        </div>

                        <div class="dropdown">
                            <button type="button" class="dropbtn">Año: <span id="year-label">1500</span> <span>▼</span></button>
                            <div class="dropdown-content" style="padding: 20px;">
                                <input type="range" name="anio" id="year-slider" 
                                    min="1500" max="2026" value="1500" 
                                    style="width: 100%;">
                                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 5px;">
                                    <span>1500</span>
                                    <span>2026</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">Filtrar Resultados</button>
                                <button type="button" id="btn-ver-tabla" class="btn-flotante-tabla">
                                    Ver Lista de Datos
                                </button>
                    </div>
                </form>
        </aside>

        <div class="zona-derecha">
            <div>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <div class="contenedor-tabla">
                        <table class="tabla-resultados">
                            <thead>
                                <tr>
                                    <th class="col-id">Record Guid</th>
                                    <th class="col-genero">Collectors</th>
                                    <th class="col-especie">CountryName</th>
                                    <th class="col-localidad">CalcFullName</th>
                                    <th class="col-pais">GenusSection</th>
                                    <th class="col-pais">CollectionYear</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td class="col-id"><?php echo htmlspecialchars($row['Record Guid']); ?></td>
                                        <td class="col-genero"><?php echo htmlspecialchars($row['Collectors']); ?></td>
                                        <td class="col-especie"><?php echo htmlspecialchars($row['CountryName']); ?></td>
                                        <td class="col-localidad"><?php echo htmlspecialchars($row['CalcFullName']); ?></td>
                                        <td class="col-pais"><?php echo htmlspecialchars($row['GenusSection']); ?></td>
                                        <td class="col-pais"><?php echo htmlspecialchars($row['CollectionYear']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <div class="info-resultados">
                    Mostrando resultados del <strong><?php echo ($total_registros > 0) ? $inicio_limite + 1 : 0; ?></strong> al 
                    <strong><?php echo min($inicio_limite + $resultados_por_pagina, $total_registros); ?></strong> 
                    de <strong><?php echo $total_registros; ?></strong>.
                </div>                    

                    

                <?php else: ?>
                    <div class="sin-resultados">
                        <p>No hay datos que mostrar. Ajusta los filtros y pulsa "Filtrar".</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="paginacion-container">
                <div class="paginacion">
                    <?php
                    $url_base = "?buscador=" . urlencode($termino) . "&pagina=";
                    $rango = 5; // Tamaño del bloque de páginas a mostrar

                    // Calcular en qué bloque estamos (1-5, 6-10, 11-15, etc.)
                    $bloque_actual = ceil($pagina_actual / $rango);
                    $inicio_bloque = (($bloque_actual - 1) * $rango) + 1;
                    $fin_bloque = min($inicio_bloque + $rango - 1, $total_paginas);

                    // --- Botón Anterior (Solo si no estamos en la página 1) ---
                    if ($pagina_actual > 1) {
                        echo "<a href='{$url_base}" . ($pagina_actual - 1) . "'>Previous</a>";
                    }

                    // --- Botón "..." para ir al bloque anterior (Solo si no estamos en el primer bloque) ---
                    if ($inicio_bloque > 1) {
                        $salto_atras = $inicio_bloque - 1;
                        echo "<a href='{$url_base}{$salto_atras}' class='dots'>...</a>";
                    }

                    // --- Números del bloque actual ---
                    for ($i = $inicio_bloque; $i <= $fin_bloque; $i++) {
                        $clase = ($i == $pagina_actual) ? "class='active'" : "";
                        echo "<a href='{$url_base}{$i}' $clase>$i</a>";
                    }

                    // --- Botón "..." para ir al bloque siguiente (Solo si hay más páginas después del bloque actual) ---
                    if ($fin_bloque < $total_paginas) {
                        $salto_adelante = $fin_bloque + 1;
                        echo "<a href='{$url_base}{$salto_adelante}' class='dots'>...</a>";
                    }

                    // --- Botón Siguiente (Solo si no estamos en la última página) ---
                    if ($pagina_actual < $total_paginas) {
                        echo "<a href='{$url_base}" . ($pagina_actual + 1) . "'>Next</a>";
                    }
                    ?>
                </div>
            </div>

            <footer>
                <a href="https://www.csic.es/es">
                    <img src="img/CSIC.png" alt="CSIC" class="logo2">
                </a>
                <p>Copyright © Flora de Guinea Ecuatorial 2026</p>
                <a href="https://www.ucm.es/">
                    <img src="img/complutense.png" alt="Complutense" class="logo2">
                </a>
            </footer>
        </div>
    </div>


<!--Icono hamburguesa-->
<script>
    const btnFiltros = document.getElementById('btn-toggle-filtros'); // Capturamos el botón de filtros

    hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        const isOpen = navLinks.classList.contains('active');
        
        hamburger.innerHTML = isOpen ? '✕' : '☰';
        document.body.style.overflow = isOpen ? 'hidden' : 'auto';

        // --- NUEVA LÓGICA ---
        // Si el menú hamburguesa se abre, ocultamos el botón de filtros manualmente
        if (btnFiltros) {
            btnFiltros.style.display = isOpen ? 'none' : 'flex';
        }
    });
</script>

<script>
    // Manejo de dropdowns
    document.addEventListener("click", function(event) {
        const isButton = event.target.matches('.dropbtn') || event.target.closest('.dropbtn');
        if (isButton) {
            const currentButton = event.target.closest('.dropbtn');
            currentButton.nextElementSibling.classList.toggle("show");
            event.stopPropagation();
        } else if (!event.target.closest('.dropdown-content')) {
            document.querySelectorAll('.dropdown-content').forEach(menu => menu.classList.remove('show'));
        }
    });
    
    // Limpiar filtros
    document.querySelector('.icono-basura').addEventListener('click', function() {
        window.location.href = window.location.pathname; 
    });
</script>

<!--JS para que funcione el botón de filtros--->
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnFiltros = document.getElementById('btn-toggle-filtros');
            const sidebar = document.getElementById('sidebar-filtros');
            const btnFlecha = document.getElementById('btn-cerrar-flecha'); // La nueva flecha
            const btnSubmit = sidebar.querySelector('.btn-submit');

            // Función centralizada para CERRAR
            const cerrarSidebar = () => {
                sidebar.classList.remove('active');
                btnFiltros.innerHTML = 'Filtros';
                document.body.style.overflow = 'auto';
            };

            // 1. Abrir/Cerrar con el botón principal (Filtros / X)
            btnFiltros.addEventListener('click', (e) => {
                e.stopPropagation(); // Importante para que no se considere "clic fuera"
                const isOpen = sidebar.classList.toggle('active');
                btnFiltros.innerHTML = isOpen ? '✕' : 'Filtros';
                document.body.style.overflow = isOpen ? 'hidden' : 'auto';
            });

            // 2. Cerrar al pulsar la FLECHA IZQUIERDA
            if (btnFlecha) {
                btnFlecha.addEventListener('click', () => {
                    cerrarSidebar();
                });
            }

            // 3. Cerrar al pulsar FUERA del formulario
            document.addEventListener('click', (event) => {
                // Si la sidebar está abierta...
                if (sidebar.classList.contains('active')) {
                    // ...y el clic NO fue dentro de la sidebar ni en el botón principal
                    if (!sidebar.contains(event.target) && !btnFiltros.contains(event.target)) {
                        cerrarSidebar();
                    }
                }
            });

            // 4. Cerrar al pulsar "Filtrar Resultados" (opcional)
            if (btnSubmit) {
                btnSubmit.addEventListener('click', () => {
                    cerrarSidebar();
                });
            }
        });
</script>

<script>
    // 1. Preparar datos de PHP a JS
    const puntosIpomoea = [
        <?php 
        mysqli_data_seek($resultado, 0); 
        while($fila = $resultado->fetch_assoc()): 
        ?>
        {
            lat: <?php echo (float)($fila['DecimalLatitude'] ?? 0); ?>,
            lng: <?php echo (float)($fila['DecimalLongitude'] ?? 0); ?>,
            utm: "<?php echo $fila['UTM_Grid'] ?? ''; ?>", 
            nombre: "<?php echo addslashes($fila['CalcFullName']); ?>",
            colector: "<?php echo addslashes($fila['Collectors']); ?>",
            pais: "<?php echo addslashes($fila['CountryName']); ?>"
        },
        <?php endwhile; ?>
    ];

    function inicializarMapa() {
        const esMovil = L.Browser.mobile;
        const mapDiv = document.getElementById('mapa');
        
        // Inicializar Leaflet con tu configuración de seguridad
        const map = L.map('mapa', {
            scrollWheelZoom: false,
            dragging: !esMovil,
            tap: !esMovil,
            touchZoom: esMovil ? 'center' : true
        }).setView([0, 0], 2);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; Flora de Guinea Ecuatorial'
        }).addTo(map);

        const bounds = L.latLngBounds();
        const iconoRojo = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        // Pintar todos los marcadores
        puntosIpomoea.forEach(p => {
            let pLat = p.lat, pLng = p.lng;
            if ((pLat === 0 || pLng === 0) && p.utm !== "") {
                try {
                    const coords = mgrs.toPoint(p.utm.replace(/\s+/g, '').toUpperCase().trim());
                    pLng = coords[0]; pLat = coords[1];
                } catch(e) {}
            }

            if (pLat !== 0 && pLng !== 0) {
                L.marker([pLat, pLng], { icon: iconoRojo }).addTo(map)
                    .bindPopup(`<b>${p.nombre}</b><br>País: ${p.pais}<br><small>Coll: ${p.colector}</small>`);
                bounds.extend([pLat, pLng]);
            }
        });

        if (bounds.isValid()) map.fitBounds(bounds, { padding: [50, 50] });

        // --- Lógica de avisos y Ctrl (reutilizada de tu otro script) ---
        // (Aquí pegas tus funciones mostrarAviso, mapDiv.addEventListener('wheel'), etc.)

        // Gestión del panel de la tabla
        const btnVer = document.getElementById('btn-ver-tabla');
        const btnCerrar = document.getElementById('btn-cerrar-tabla');
        const panel = document.getElementById('panel-tabla');

        btnVer.onclick = () => panel.classList.add('active');
        btnCerrar.onclick = () => panel.classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', inicializarMapa);
</script>
</body>
</html>