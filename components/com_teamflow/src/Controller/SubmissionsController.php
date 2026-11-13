<?php

namespace Joomla\Component\Teamflow\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;

class SubmissionsController extends BaseController
{
    public function submit(): void
    {
        $app = Factory::getApplication();
        $savedFile = null;

        if ($this->input->getMethod() !== 'POST') {
            throw new \RuntimeException(
                'Phương thức gửi không hợp lệ.',
                405
            );
        }

        if (!Session::checkToken('post')) {
            throw new \RuntimeException(
                'Phiên gửi bài không hợp lệ.',
                403
            );
        }

        $user = $app->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException(
                'Bạn cần đăng nhập trước khi nộp bài.',
                403
            );
        }

        $assignment_id = $this->input->post->getInt(
            'assignment_id',
            0
        );

        $link_url = trim(
            $this->input->post->getString('link_url', '')
        );

        if ($assignment_id <= 0) {
            throw new \RuntimeException(
                'Nhiệm vụ không hợp lệ.'
            );
        }

        try {
            // Đọc file từ biểu mẫu.
            $file = $this->input->files->get(
                'submission_file',
                null,
                'raw'
            );

            $hasFile = is_array($file)
                && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)
                    !== UPLOAD_ERR_NO_FILE;

            if (!$hasFile && $link_url === '') {
                throw new \RuntimeException(
                    'Vui lòng chọn file hoặc nhập liên kết bài làm.'
                );
            }

            if ($link_url !== '') {
                $scheme = strtolower(
                    (string) parse_url(
                        $link_url,
                        PHP_URL_SCHEME
                    )
                );

                if (
                    !filter_var($link_url, FILTER_VALIDATE_URL)
                    || !in_array(
                        $scheme,
                        ['http', 'https'],
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        'Liên kết bài làm không hợp lệ.'
                    );
                }
            }

            $filePath = null;

            if ($hasFile) {
                if (
                    (int) $file['error']
                    !== UPLOAD_ERR_OK
                ) {
                    throw new \RuntimeException(
                        'Tải tệp lên thất bại. Mã lỗi: '
                        . (int) $file['error']
                    );
                }

                $tmpName = (string) (
                    $file['tmp_name'] ?? ''
                );

                $size = (int) ($file['size'] ?? 0);

                if (
                    !is_uploaded_file($tmpName)
                    || $size <= 0
                    || $size > 10 * 1024 * 1024
                ) {
                    throw new \RuntimeException(
                        'Tệp không hợp lệ hoặc vượt quá 10 MB.'
                    );
                }

                $extension = strtolower(
                    pathinfo(
                        (string) ($file['name'] ?? ''),
                        PATHINFO_EXTENSION
                    )
                );

                if (!in_array(
                    $extension,
                    ['pdf', 'docx', 'zip'],
                    true
                )) {
                    throw new \RuntimeException(
                        'Chỉ chấp nhận PDF, DOCX hoặc ZIP.'
                    );
                }

                // Kiểm tra nội dung tệp.
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpName);

                if (
                    $extension === 'pdf'
                    && $mime !== 'application/pdf'
                ) {
                    throw new \RuntimeException(
                        'Nội dung tệp PDF không hợp lệ.'
                    );
                }

                if (
                    in_array($extension, ['docx', 'zip'], true)
                ) {
                    if (!class_exists(\ZipArchive::class)) {
                        throw new \RuntimeException(
                            'Máy chủ chưa bật PHP Zip.'
                        );
                    }

                    $zip = new \ZipArchive();

                    if (
                        $zip->open($tmpName)
                        !== true
                    ) {
                        throw new \RuntimeException(
                            'Tệp ZIP/DOCX không hợp lệ.'
                        );
                    }

                    if ($extension === 'docx') {
                        $isDocx =
                            $zip->locateName(
                                '[Content_Types].xml'
                            ) !== false
                            && $zip->locateName(
                                'word/document.xml'
                            ) !== false;

                        if (!$isDocx) {
                            $zip->close();

                            throw new \RuntimeException(
                                'Nội dung tệp DOCX không hợp lệ.'
                            );
                        }
                    }

                    $zip->close();
                }

                // Lưu tệp bên ngoài thư mục htdocs.
                $storageDir = dirname(
                    JPATH_ROOT,
                    2
                ) . '/teamflow-private-uploads';

                if (
                    !is_dir($storageDir)
                    && !mkdir(
                        $storageDir,
                        0700,
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        'Không tạo được thư mục lưu tệp.'
                    );
                }

                if (!is_writable($storageDir)) {
                    throw new \RuntimeException(
                        'Thư mục lưu tệp không có quyền ghi.'
                    );
                }

                $newName = bin2hex(
                    random_bytes(16)
                ) . '.' . $extension;

                $savedFile = $storageDir
                    . '/' . $newName;

                if (!move_uploaded_file(
                    $tmpName,
                    $savedFile
                )) {
                    throw new \RuntimeException(
                        'Không thể lưu tệp bài làm.'
                    );
                }

                // Chỉ lưu tên tệp vào CSDL.
                $filePath = $newName;
            }

            $model = $this->getModel('Submission');

            if (!$model) {
                throw new \RuntimeException(
                    'Không tìm thấy SubmissionModel.'
                );
            }

            $id = $model->saveSubmission(
                $assignment_id,
                $filePath,
                $link_url !== '' ? $link_url : null
            );

            $app->enqueueMessage(
                'Nộp bài thành công. Mã bài nộp: ' . $id,
                'success'
            );

        } catch (\Throwable $e) {

            // Xóa tệp nếu lưu CSDL thất bại.
            if (
                $savedFile !== null
                && is_file($savedFile)
            ) {
                unlink($savedFile);
            }

            $app->enqueueMessage(
                $e->getMessage(),
                'error'
            );
        }

        $this->setRedirect(
            Route::_(
                'index.php?option=com_teamflow&view=submission',
                false
            )
        );
    }

public function download(): void
{
    $app = Factory::getApplication();
    $user = $app->getIdentity();

    if ($this->input->getMethod() !== 'GET') {
        throw new \RuntimeException(
            'Phương thức không hợp lệ.',
            405
        );
    }

    if (!Session::checkToken('get')) {
        throw new \RuntimeException(
            'Liên kết tải không hợp lệ.',
            403
        );
    }

    if (!$user || $user->guest) {
        throw new \RuntimeException(
            'Vui lòng đăng nhập.',
            403
        );
    }

    $submissionId = $this->input->getInt(
        'submission_id',
        0
    );

    $model = $this->getModel('Submission');

    if (!$model) {
        throw new \RuntimeException(
            'Không tìm thấy SubmissionModel.'
        );
    }

    $submission = $model->getSubmissionForDownload(
        $submissionId,
        (int) $user->id
    );

    if (!$submission) {
        throw new \RuntimeException(
            'Bạn không có quyền tải tệp này.',
            403
        );
    }

    $fileName = (string) (
        $submission['file_path'] ?? ''
    );

    if (!preg_match(
        '/^[a-f0-9]{32}\.(pdf|docx|zip)$/',
        $fileName
    )) {
        throw new \RuntimeException(
            'Tên tệp không hợp lệ.',
            404
        );
    }

    $storageDir = dirname(
        JPATH_ROOT,
        2
    ) . '/teamflow-private-uploads';

    $basePath = realpath($storageDir);
    $filePath = realpath(
        $storageDir . '/' . $fileName
    );

    if (
        $basePath === false
        || $filePath === false
        || dirname($filePath) !== $basePath
        || !is_file($filePath)
        || !is_readable($filePath)
    ) {
        throw new \RuntimeException(
            'Không tìm thấy tệp.',
            404
        );
    }

    $mimeTypes = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip' => 'application/zip'
    ];

    $extension = strtolower(
        pathinfo($fileName, PATHINFO_EXTENSION)
    );

    while (ob_get_level() > 0) {
        if (!ob_end_clean()) {
            break;
        }
    }

    header('Content-Type: ' . $mimeTypes[$extension]);
    header(
        'Content-Disposition: attachment; filename="' .
        $fileName . '"'
    );
    header('Content-Length: ' . filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    readfile($filePath);
    exit;
}

}
