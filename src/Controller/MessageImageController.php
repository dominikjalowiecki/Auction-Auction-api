<?php

namespace App\Controller;

require_once(__DIR__ . '/../config.php');

use App\Controller\{Controller, AuthenticationController};
use App\Model\MessageImageModel;
use App\Response;

class MessageImageController extends Controller
{
    public function __construct($db_connection, $endpoint, $request_method)
    {
        parent::__construct($db_connection, $endpoint, $request_method, new MessageImageModel($db_connection));
    }

    public function processRequest()
    {
        switch ($this->endpoint[0]) {
            case '':
                header("Access-Control-Allow-Methods: POST, OPTIONS");
                switch ($this->request_method) {
                    case 'POST':
                        $token_data = AuthenticationController::authenticate($this->db_connection);

                        if ($token_data) {
                            $this->model->id_user = $token_data->data->id;
                            $options = array(
                                'options' => array(
                                    'min_range' => 1
                                )
                            );

                            $dir_size = 0;
                            $max_dir_size = 536870912; // 512 MB
                            $max_image_size = 2097152; // 2 MB
                            $upload_dir = __DIR__ . "/../../uploads";

                            foreach (glob($upload_dir . "/*", GLOB_NOSORT) as $each) {
                                $dir_size += is_file($each) ? filesize($each) : 0;
                            }

                            if ($dir_size > $max_dir_size - $max_image_size) {
                                error_log('Image upload folder is full...');
                                return Response::serviceUnavailable();
                            }

                            if (
                                !empty($this->model->id_message = trim($_POST['id_message'] ?? null)) &&
                                filter_var($this->model->id_message, FILTER_VALIDATE_INT, $options) &&
                                (isset($_FILES['image']['error']) ||
                                    !is_array($_FILES['image']['error'])) &&
                                $_FILES['image']['error'] === UPLOAD_ERR_OK &&
                                $_FILES['image']['size'] <= $max_image_size
                            ) {
                                if ($this->model->checkIfLessThanOne() & $this->model::FLAG_FAILURE) {
                                    return Response::serviceUnavailable();
                                }

                                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                if (
                                    false !== ($ext = array_search(
                                        $finfo->file($_FILES['image']['tmp_name']),
                                        array(
                                            'jpg' => 'image/jpeg',
                                            'png' => 'image/png',
                                            'gif' => 'image/gif',
                                        ),
                                        true
                                    ))
                                ) {
                                    if (is_uploaded_file($_FILES['image']['tmp_name'])) {
                                        $hash = sha1_file($_FILES['image']['tmp_name']);
                                        $path = $upload_dir . '/' . $hash . "." . $ext;
                                        $this->model->path = CONFIG['BASE_BACKEND_URL'] . '/uploads/' . $hash . "." . $ext;
                                        if (file_exists($path) || move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
                                            $status = $this->model->addImage();
                                            if ($status & $this->model::FLAG_SUCCESS) {
                                                return Response::created();
                                            } elseif ($status & $this->model::FLAG_MESSAGE_DOESNT_EXIST) {
                                                unlink($path);
                                                return Response::unprocessableEntity();
                                            } elseif ($status & $this->model::FLAG_FAILURE) {
                                                unlink($path);
                                                return Response::serviceUnavailable();
                                            }
                                        } else {
                                            return Response::badRequest();
                                        }
                                    } else {
                                        return Response::badRequest();
                                    }
                                } else {
                                    return Response::badRequest();
                                }
                            } else {
                                return Response::badRequest();
                            }
                        }
                        break;
                    case 'OPTIONS':
                        return Response::noContent();
                    default:
                        return Response::methodNotAllowed("POST, OPTIONS");
                }
                break;
            default:
                return Response::notFound();
        }
    }
}
