<?php
if (!defined('ABSPATH')) {
    exit;
}

trait QTV_Response_Helper {
    /**
     * Chuẩn hoá response trả về API
     *
     * @param mixed  $data    Dữ liệu trả về (array/object)
     * @param string $message Thông báo
     * @param int    $code    Mã code HTTP
     * @param string $lang    Ngôn ngữ (default: vi)
     * @return WP_REST_Response
     */
    protected function apiResponse($data = null, $message = "", $code = 200, $lang = "vi") {
        if ($data === null) {
            $data = (object)[];
        }

        return rest_ensure_response([
            'code'    => $code,
            'lang'    => $lang,
            'message' => $message,
            'data'    => $data
        ]);
    }

    /**
     * Trả về response success (200)
     */
    protected function success($data = null, $message = "Request thành công", $lang = "vi") {
        return $this->apiResponse($data, $message, 200, $lang);
    }

    /**
     * Trả về response lỗi (4xx, 5xx)
     */
    protected function error($message = "Có lỗi xảy ra", $code = 400, $data = null, $lang = "vi") {
        return $this->apiResponse($data, $message, $code, $lang);
    }

    /**
     * Trả về lỗi 500 (Internal Server Error)
     */
    protected function serverError($message = "Lỗi hệ thống", $data = null, $lang = "vi") {
        return $this->apiResponse($data, $message, 500, $lang);
    }
}
