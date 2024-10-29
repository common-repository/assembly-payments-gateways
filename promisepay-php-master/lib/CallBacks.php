<?php
namespace PromisePay;

class CallBacks {

    public function listCallBack($params) {
        PromisePay::RestClient('get', 'callbacks/' . $params);

        return PromisePay::getDecodedResponse('callbacks');
    }

    public function create($params) {
        PromisePay::RestClient('post', 'callbacks?', $params);

        return PromisePay::getDecodedResponse('callbacks');
    }

    public function show($id) {
        PromisePay::RestClient('get', 'callbacks/'. $id);

        return PromisePay::getDecodedResponse('callbacks');
    }

    public function update($params) {
        PromisePay::RestClient('patch', 'callbacks/', $params);

        return PromisePay::getDecodedResponse('callbacks');
    }

    public function listCallbackResponses($id) {
        PromisePay::RestClient('get', 'callbacks/'. $id .'/responses');

        return PromisePay::getDecodedResponse('callbacks');
    }

    public function showCallbackResponses($callback_id, $id) {
        PromisePay::RestClient('get', 'callbacks/'. $callback_id . '/responses/'. $id);

        return PromisePay::getDecodedResponse('callbacks');
    }


    public function delete($id) {
        PromisePay::RestClient('delete', 'callbacks/' . $id);
        return PromisePay::getDecodedResponse('callbacks');
    }

//    public function redact($id) {
//        return $this->delete($id);
//    }
//
//    public function getUser($id) {
//        PromisePay::RestClient('get', 'callbacks/' . $id . '/users');
//
//        return PromisePay::getDecodedResponse('users');
//    }
}
