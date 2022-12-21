<?php

class ComgatePaymentsSimpleDatabase {

    private $_merchant;
    private $_test;

    /**
     * @param string $dataFolderName
     *      folder name where to save data
     * @param string $merchant
     *      merchants identifier
     * @param boolean $test
     *      TRUE = testing system variant
     *      FALSE = release (production) system variant
     */
    public function __construct($merchant, $test) {
        $this->_merchant = $merchant;
        $this->_test = $test;
    }

    /**
     * returns next numeric identifier for a merchant transaction
     *
     * @return int
     * @throws Exception
     */
    public function createNextRefId($email = null, $phone = null, $firstname = null, $surname = null, $country = null, $type = null) {
        $o = new \API\BaseObject("payment");
        $o->setValue("email", $email);
        $o->setValue("phone_number", $phone);
        $o->setValue("firstname", $firstname);
        $o->setValue("surname", $surname);
        $o->setValue("country", $country);
        $o->setValue("is_test", intval($this->_test));
        $o->setValue("payment_type", $type);
        $o->setValue("payment_status", "CREATED");
        $o->setValue("payment_lang_id", $_SESSION[SESS_LANG]["id"]);

        $o->save();
        $refId = $o->getId();


        return $refId;
    }

    /**
     * store the transaction data in a data file
     *
     * @param string $transId
     * @param string $refId
     * @param float $price
     * @param string $currency
     * @param string $status
     * @param string $fee
     *
     * @throws Exception
     */
    public function saveTransaction($transId, $refId, $price, $currency, $status, $fee = 0) {
        $o = new \API\BaseObject("payment", $refId);
        $o->setValue("is_test", intval($this->_test));
        $o->setValue("amount", $price);
        $o->setValue("currency", $currency);
        $o->setValue("payment_status", $status);
        $o->setValue("fee", floatval($fee));
        $o->setValue("trans_id", $transId);
        $o->save();
    }

    /**
     * returns transaction status from a data file
     *
     * @param string $transId
     * @param string $refId
     *
     * @return string
     * @throws Exception
     */
    public function getTransactionStatus($transId, $refId) {
        $o = \API\BaseObject::getObjectByAttr("trans_id", $transId);

        if (empty($o) || $o->getId() != $refId) {
            throw new Exception('Unknown transaction');
        }

        return $o->getValue("payment_status");
    }

    /**
     * checks transaction parameters in a data file
     *
     * @param string $transId
     * @param string $refId
     * @param float $price
     * @param string $currency
     *
     * @throws Exception
     */
    public function checkTransaction($transId, $refId, $price, $currency) {
        $o = \API\BaseObject::getObjectByAttr("trans_id", $transId);

        if (empty($o) || $o->getId() != $refId) {
            throw new Exception('Unknown transaction');
        }

        if (
            $o->getValue("is_text") !== $this->_test ||
            $o->getValue("amount") !== $price ||
            $o->getValue("currency") !== $currency
        ) {
            throw new Exception('Invalid payment parameters');
        }
    }

}