<?php
// Permitir solicitudes desde cualquier origen (opcional, ajusta según tu seguridad)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");  

// ==========================================
// CONFIGURACIÓN BÁSICA
// ==========================================
$dbPath = __DIR__ . '/kardex.db'; 
$remotePass = 'tu_password_seguro';                      //El mismo pass de configuracion
$jwtSecret = 'Clave_Super_Secreta_Kardex_2026_Cambia_Esto'; 

// ==========================================
// 1. CAPTURAR PAYLOAD
// ==========================================
$input = json_decode(file_get_contents('php://input'), true);
//log_text($input);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(["error" => "Petición inválida o vacía"]);
    exit;
}

$action = $input['action'];

if($action == 'ping'){
  echo json_encode(["success" => "Pong"]);
  die();
}

if($action == 'validepass'){
    log_text("Validate Pass: {$input['pass']} == $remotePass");
    if($input['pass']===$remotePass){
      echo json_encode(["success" => "ok"]);
    }else{
      http_response_code(401);
      echo json_encode(["error" => "error"]);
    }   
  die();
}

// ==========================================
// 2. AUTENTICACIÓN (LOGIN)
// ==========================================
if ($action === 'login') {
    if ($input['pass'] === $remotePass) {
        // Generar un token firmado que expira en 72 horas
        $exp = time() + (72 * 3600);
        $payloadBase = base64_encode(json_encode(['exp' => $exp]));
        $signature = hash_hmac('sha256', $payloadBase, $jwtSecret);
        $token = $payloadBase . '.' . $signature;
        //log_text("login ok");
        backup();
        echo json_encode(["token" => $token, "expires_in_hours" => 8]);
    } else {
        //log_text("login error");
        http_response_code(401);
        echo json_encode(["error" => "Credenciales incorrectas"]);
    }
    exit;
}

// ==========================================
// 3. VALIDACIÓN DE TOKEN DE SEGURIDAD
// ========================================== 
if (!isset($input['token'])) {
    //log_text("Token no proporcionado");
    http_response_code(401);
    echo json_encode(["error" => "Token no proporcionado"]);
    exit;
}



$tokenParts = explode('.', $input['token']);
if (count($tokenParts) !== 2) {
    //log_text("Formato de token inválido");
    http_response_code(401);
    echo json_encode(["error" => "Formato de token inválido"]);
    exit;
}

$payloadDecoded = json_decode(base64_decode($tokenParts[0]), true);
$expectedSignature = hash_hmac('sha256', $tokenParts[0], $jwtSecret);

// Validamos que nadie haya alterado el token y que no esté expirado
if ($tokenParts[1] !== $expectedSignature || $payloadDecoded['exp'] < time()) {
    //log_text("Token inválido o expirado");
    http_response_code(401);
    echo json_encode(["error" => "Token inválido o expirado"]);
    exit;
}

// ==========================================
// 4. CONEXIÓN PDO A SQLITE
// ==========================================
try {
    $pdo = new PDO("sqlite:" . $dbPath);
    // Configurar PDO para que lance excepciones en caso de error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Devolver arreglos asociativos para que json_encode genere la estructura correcta para Newtonsoft.Json
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    //log_text("Error de conexión DB:");
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión DB: " . $e->getMessage()]);
    exit;
}

$queries = $input['queries'] ?? [];

// ==========================================
// 5. MOTOR DE EJECUCIÓN (ACCIONES)
// ==========================================
try {
    switch ($action) {
        
        case 'nonquery':
            list($sql, $params) = prepararConsultaCsharp($queries[0]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->rowCount());
            break;

        case 'query':
            list($sql, $params) = prepararConsultaCsharp($queries[0]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            echo json_encode($result);
            break;

        case 'scalar':
            list($sql, $params) = prepararConsultaCsharp($queries[0]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchColumn();
            echo json_encode($result !== false ? $result : null);
            break;

        case 'transaction':
            $pdo->beginTransaction();
            $affectedRows = 0;
            foreach ($queries as $q) {
                list($sql, $params) = prepararConsultaCsharp($q);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $affectedRows += $stmt->rowCount();
            }
            $pdo->commit();
            echo json_encode($affectedRows);
            break;
        case 'ping':
            echo json_encode(["success" => "Pong"]);
            break;
        case 'download':
            download();
            break;
        case 'restore':
            if (!isset($input['database_base64'])) {
                http_response_code(400);
                echo json_encode(["error" => "No se recibió el archivo en base64"]);
                exit;
            }
        
            $dbPath = __DIR__ . '/kardex.db';
            $fileData = base64_decode($input['database_base64']);
        
            try {
                // Backup
                backup("-Restore_" . date('Y-m-d_His'));
                
                // Guardar archivo
                if (file_put_contents($dbPath, $fileData) !== false) {
                    echo json_encode(["success" => true, "message" => "Restauración exitosa"]);
                } else {
                    throw new Exception("Error al escribir el archivo en el servidor");
                }
            } catch (Exception $e) {
                //http_response_code(500);
                echo json_encode(["success" => false, "error" => $e->getMessage()]);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(["error" => "Acción desconocida"]);
            break;
    }
} catch (PDOException $e) {
    // Si falla cualquier consulta y hay una transacción en curso, cancelamos todo
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }     
    // Retornamos error 500 para que C# lance la excepción y el catch del UsingTransaction actúe            
    http_response_code(500);
    log_text( "Error SQLite Server ($action) {$q['Parameters']}: " . $e->getMessage() );
    echo json_encode(["error" => "Error SQLite Server ($action) {$q['Parameters']}: " . $e->getMessage()]);
}

// ==========================================
// 5. MOTOR DE EJECUCIÓN (ACCIONES)
// ==========================================

// Función traductora de dialectos SQL (C# a PDO)
function prepararConsultaCsharp($q) {
    $sql = $q['Query'];
    $params = [];
    
    if (!empty($q['Parameters'])) {
        // Ordenamos las llaves por longitud descendente para evitar que 
        // reemplazar '@user' corrompa un parámetro llamado '@user_id'
        $keys = array_keys($q['Parameters']);
        usort($keys, function($a, $b) { return strlen($b) - strlen($a); });
        
        foreach ($keys as $key) {
            $newKey = str_replace('@', ':', $key); // Convertimos '@' a ':'
            $params[$newKey] = $q['Parameters'][$key];
            $sql = str_replace($key, $newKey, $sql); // Reemplazamos en el texto SQL
        }
    }
    return [$sql, $params];
}

function download(){
    global $dbPath;
    if (!file_exists($dbPath)) {
        http_response_code(404);
        echo json_encode(["error" => "Archivo de base de datos no encontrado"]);
        exit;
    }
    
    if (!is_readable($dbPath)) {
        http_response_code(403);
        echo json_encode(["error" => "No hay permisos para leer la base de datos"]);
        exit;
    }

    $fileSize = filesize($dbPath);        
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"kardex_backup_" . date('Y-m-d_His') . ".db\"");
    header("Content-Length: " . $fileSize);
    header("Content-Transfer-Encoding: binary");
    header("Cache-Control: must-revalidate");
    header("Pragma: public");
    
    ob_clean();
    flush();

    readfile($dbPath);
    exit; 
}

function restore(){
   if (!isset($_POST['token']) || !validarToken($_POST['token'])) {
        http_response_code(401);
        echo json_encode(["error" => "Token inválido o expirado"]);
        exit;
    }
    
    // Verificar que se subió un archivo
    if (!isset($_FILES['database']) || $_FILES['database']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["error" => "No se recibió el archivo de base de datos"]);
        exit;
    }
    
    $uploadedFile = $_FILES['database'];
    $dbPath = __DIR__ . '/kardex.db';
    
    try {
        // Crear backup del archivo actual
        backup("-Restore_" . date('Y-m-d_His'));
        
        // Mover el archivo subido a la ubicación de la BD
        if (move_uploaded_file($uploadedFile['tmp_name'], $dbPath)) {
            echo json_encode([
                "success" => true,
                "message" => "Base de datos restaurada exitosamente. Backup creado en: " . basename($backupPath)
            ]);
        } else {
            throw new Exception("No se pudo mover el archivo subido");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al restaurar: " . $e->getMessage()]);
    }
    exit;
}

function backup($pref = "")
{
    $dbPath = __DIR__ . '/kardex.db';
    $backupFolder = __DIR__ . '/backs';
    
    if (!file_exists($dbPath)) return;
    if (!is_dir($backupFolder)) mkdir($backupFolder, 0755, true);
    
    $dia = date('d');
    $nombreFile = $pref ? "{$dia}-{$pref}.db" : "{$dia}.db";
    
    copy($dbPath, $backupFolder . '/' . $nombreFile);
    
    // Limpiar backups de más de 30 días
    $threshold = time() - (30 * 86400);
    foreach (glob($backupFolder . '/*.db') as $file) {
        if (filectime($file) < $threshold) @unlink($file);
    }
}

function log_text($msg) {
    if (is_array($msg)) {
        $msg = json_encode($msg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    file_put_contents('log.txt', $msg . PHP_EOL, FILE_APPEND);
}
