<?php
header('Content-Type: application/json');

if (defined('DB_SERVER')) {
    $db_server = DB_SERVER;
    $db_username = DB_USERNAME;
    $db_password = DB_PASSWORD;
    $db_name = DB_NAME;
} else {
    $db_server = 'localhost';
    $db_username = 'root'; 
    $db_password = '';     
    $db_name = 'db_kontak_sederhana'; 
}

$conn = new mysqli($db_server, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    http_response_code(500); 
    die(json_encode(['status'=>'error','message'=>'Koneksi database gagal: '.$conn->connect_error]));
}

function validate_input($data){
    $errors = [];
    if(empty($data['nama'])){
        $errors[] = 'Nama tidak boleh kosong';
    }
    if(empty($data['telepon']) || !preg_match('/^[0-9]+$/', $data['telepon'])){
        $errors[] = 'Nomor Telepon hanya boleh angka';
    }
    if(!empty($data['email'])){
        if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
            $errors[] = 'Format email tidak valid';
        } 
        elseif(!preg_match('/@gmail\.com$/', $data['email'])){
            $errors[] = 'Format email tidak valid'; 
        }
    }
    return $errors;
}

function get_input_data() {
    $input_data = file_get_contents('php://input');
    if (isset($GLOBALS['mock_input'])) {
        $input_data = $GLOBALS['mock_input'];
    }
    return json_decode($input_data, true);
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    case 'GET':
        $search = $_GET['search'] ?? '';
        $sql = "SELECT id, nama, telepon, email FROM kontak";
        
        $searchTerm = null;
        if(!empty($search)){
            $sql .= " WHERE nama LIKE ? OR telepon LIKE ?";
            $searchTerm = '%'.$search.'%';
        }
        $sql .= " ORDER BY nama ASC";

        $stmt = $conn->prepare($sql);
        
        if(!empty($search)){
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $kontak = [];
        while($row = $result->fetch_assoc()){
            $kontak[] = $row;
        }
        echo json_encode(['status'=>'success','data'=>$kontak]);
        break;

    case 'POST':
        $data = get_input_data();

        $errors = validate_input($data);
        if(!empty($errors)){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>implode(', ', $errors)]);
            break;
        }
        $sql = "INSERT INTO kontak (nama, telepon, email) VALUES (?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $data['nama'], $data['telepon'], $data['email']);
        if($stmt->execute()){
            echo json_encode(['status'=>'success','message'=>'Kontak berhasil disimpan', 'id' => $stmt->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>$stmt->error]);
        }
        break;

    case 'PUT':
        $data = get_input_data();

        if(!isset($data['id'])){
            http_response_code(400); 
            echo json_encode(['status'=>'error','message'=>'ID kontak tidak ditemukan']);
            break;
        }
        $errors = validate_input($data);
        if(!empty($errors)){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>implode(', ', $errors)]);
            break;
        }
        $sql = "UPDATE kontak SET nama=?, telepon=?, email=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $data['nama'], $data['telepon'], $data['email'], $data['id']);
        if($stmt->execute()){
             if ($stmt->affected_rows > 0) {
                 echo json_encode(['status'=>'success','message'=>'Kontak berhasil diperbarui']);
            } else {
                 echo json_encode(['status'=>'error','message'=>'Gagal memperbarui: Kontak tidak ditemukan atau data sama']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>$stmt->error]);
        }
        break;

    case 'DELETE':
        $data = get_input_data();

        if(!isset($data['id'])){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'ID kontak tidak ditemukan']);
            break;
        }
        $sql = "DELETE FROM kontak WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $data['id']);
        if($stmt->execute()){
            if ($stmt->affected_rows > 0) {
                 echo json_encode(['status'=>'success','message'=>'Kontak berhasil dihapus']);
            } else {
                 echo json_encode(['status'=>'error','message'=>'Gagal menghapus: Kontak tidak ditemukan']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>$stmt->error]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['status'=>'error','message'=>'Metode tidak diizinkan']);
        break;
}
$conn->close();
?>