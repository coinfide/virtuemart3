<?php



namespace  Coinfide {

class CoinfideException extends \Exception
{

}
}


namespace  Coinfide\Entity {

use Coinfide\CoinfideException;

abstract class Base
{
    /**
     * @var array
     */
    protected $validationRules = null;

    public function validate()
    {
        if (null === $this->validationRules) {
            throw new CoinfideException('Validation rules must be set for any entity');
        }

        foreach ($this->validationRules as $field => $rule) {
            $value = call_user_func(array($this, 'get'.ucfirst($field)));

            $this->validateValue($field, $value, $rule);
        }
    }

    public function fromArray($array)
    {
        foreach ($this->validationRules as $field => $rule) {
            if (isset($array[$field])) {
                //todo: temporary to affiliate bug
                if ($array[$field] == array() && $rule['type'] == 'object') {
                    continue;
                }

                $setter = 'set' . ucfirst($field);

                call_user_func([$this, $setter], $this->createValue($array[$field], $rule));
            } elseif ($rule['required']) {
                throw new CoinfideException(
                    sprintf(
                        'Required value "%s" for class "%s" is not present in input array with keys "%s"',
                        $field,
                        get_called_class(),
                        implode(', ', array_keys($array)))
                );
            }
        }
    }

    protected function createValue($data, $rule)
    {
        switch ($rule['type']) {
            case 'list':
                $value = [];
                foreach ($data as $item) {
                    $value[] = $this->createValue($item, $rule['prototype']);
                }
                break;
            case 'object':
                $value = new $rule['class']();
                $value->fromArray($data);
                break;
            default:
                $value = $data;
                break;
        }

        return $value;
    }

    public function toArray()
    {
        $data = [];

        foreach ($this->validationRules as $field => $rule) {
            $value = call_user_func(array($this, 'get' . ucfirst($field)));

            if ($value !== null || $rule['required']) {
                switch ($rule['type']) {
                    case 'object':
                        $data[$field] = $value->toArray();
                        break;
                    case 'list':
                        $list = array();

                        foreach ($value as $item) {
                            $list[] = $item->toArray();
                        }

                        $data[$field] = $list;
                        break;
                    default:
                        $data[$field] = $value;
                        break;
                }
            }
        }

        return $data;
    }

    protected function validateValue($field, $value, $rule)
    {
        if ($rule['required'] && is_null($value)) {
            throw new CoinfideException(sprintf('Value for field "%s" is required for class "%s"', $field, get_called_class()));
        }

        if (null === $value) {
            return;
        }

        switch ($rule['type']) {
            case 'string':
                if (!is_null($value)) {
                    if (gettype($value) != 'string') {
                        throw new CoinfideException(sprintf('Provided value is not a string for field "%s" for class "%s"', $field, get_called_class()));
                    }

                    if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                        throw new CoinfideException(sprintf('Value too short for field "%s", at least %d characters required for class "%s"', $field, $rule['min_length'], get_called_class()));
                    }

                    if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                        throw new CoinfideException(sprintf('Value too long for field "%s", at most %d characters allowed for class "%s"', $field, $rule['max_length'], get_called_class()));
                    }
                }

                break;
            case 'object':
                if (!$value instanceof $rule['class']) {
                    throw new CoinfideException(sprintf('Value for field "%s" must be instance of "%s" for class "%s"', $field, $rule['class'], get_called_class()));
                }

                /* @var $value Base */
                $value->validate();
                break;
            case 'date':
                $date = \DateTime::createFromFormat('YmdHis', $value);
                if (false === $date) {
                    throw new CoinfideException(sprintf('Date "%s" does not match format "yyyyMMddHHmmss" for class "%s"', $value, get_called_class()));
                }
                break;
            case 'list':
                if (isset($rule['min_items']) && count($value) < $rule['min_items']) {
                    throw new CoinfideException(sprintf('List in field "%s" should contain at least %d item(s) for class "%s"', $field, $rule['min_items'], get_called_class()));
                }

                foreach ($value as $item) {
                    $this->validateValue($field.'[]', $item, $rule['prototype']);
                }
                break;
            case 'integer':
                if (!is_numeric($value) || round($value) != $value) {
                    throw new CoinfideException(sprintf('Value "%s" for field "%s" is not integer for class "%s"', $value, $field, get_called_class()));
                }

                break;
            case 'decimal':
                if (!is_numeric($value)) {
                    throw new CoinfideException(sprintf('Value "%s" for field "%s" is not numeric for class "%s"', $value, $field, get_called_class()));
                }

                $parts = explode('.', $value);

                if (isset($rule['digits']) && strlen($parts[0]) > $rule['digits']) {
                    throw new CoinfideException(sprintf('Value "%s" for field "%s" contains too many digits, only %d is allowed for class "%s"', $value, $field, $rule['digits'], get_called_class()));
                }

                if (isset($rule['precision']) && isset($parts[1]) && strlen($parts[1]) > $rule['precision']) {
                    throw new CoinfideException(sprintf('Value "%s" for field "%s" has too high precision, only %d digits allowed for class "%s"', $value, $field, $rule['precision'], get_called_class()));
                }

                break;
            case 'boolean':
                if ($value !== false && $value !== true) {
                    throw new CoinfideException(sprintf('Value "%s" for field "%s" for class must be either "true" or "false"', $value, $field, get_called_class()));
                }
                break;
            default:
                throw new CoinfideException(sprintf('Valdation type "%s" unknown for class "%s"', $rule['type'], get_called_class()));
                break;
        }
    }
}
}


namespace  Coinfide\Entity {

class Account extends Base
{
    protected $validationRules = array(
        'email' => array('type' => 'string', 'required' => true),
        'phone' => array('type' => 'object', 'class' => '\Coinfide\Entity\Phone', 'required' => false),
        'externalUid' => array('type' => 'string', 'max_length' => 50, 'required' => false),
        'name' => array('type' => 'string', 'max_length' => 255, 'required' => false),
        'surname' => array('type' => 'string', 'max_length' => 255, 'required' => false),
        'language' => array('type' => 'string', 'max_length' => 2, 'required' => false),
        'address' => array('type' => 'object', 'class' => '\Coinfide\Entity\Address', 'required' => false),
        'website' => array('type' => 'string', 'max_length' => 2048, 'required' => false),
        'taxpayerIdentificationNumber' => array('type' => 'string', 'max_length' => 255, 'required' => false),
        'additionalInfo' => array('type' => 'string', 'max_length' => 5000, 'required' => false),
    );

    /**
     * @var string
     */
    protected $email;

    /**
     * @var Phone
     */
    protected $phone;

    /**
     * @var string
     */
    protected $externalUid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $surname;

    /**
     * @var string
     */
    protected $language;

    /**
     * @var Address
     */
    protected $address;
    
    /**
     * @var string
     */
    protected $website;

    /**
     * @var string
     */
    protected $taxpayerIdentificationNumber;

    /**
     * @var string
     */
    protected $additionalInfo;

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return Phone
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param Phone $phone
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    /**
     * @return string
     */
    public function getExternalUid()
    {
        return $this->externalUid;
    }

    /**
     * @param string $externalUid
     */
    public function setExternalUid($externalUid)
    {
        $this->externalUid = $externalUid;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getSurname()
    {
        return $this->surname;
    }

    /**
     * @param string $surname
     */
    public function setSurname($surname)
    {
        $this->surname = $surname;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return Address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param Address $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * @param string $website
     */
    public function setWebsite($website)
    {
        $this->website = $website;
    }

    /**
     * @return string
     */
    public function getTaxpayerIdentificationNumber()
    {
        return $this->taxpayerIdentificationNumber;
    }

    /**
     * @param string $taxpayerIdentificationNumber
     */
    public function setTaxpayerIdentificationNumber($taxpayerIdentificationNumber)
    {
        $this->taxpayerIdentificationNumber = $taxpayerIdentificationNumber;
    }

    /**
     * @return string
     */
    public function getAdditionalInfo()
    {
        return $this->additionalInfo;
    }

    /**
     * @param string $additionalInfo
     */
    public function setAdditionalInfo($additionalInfo)
    {
        $this->additionalInfo = $additionalInfo;
    }
}
}


namespace  Coinfide\Entity {

class Address extends Base
{
    protected $validationRules = array(
        'countryCode' => array('type' => 'string', 'max_length' => 2, 'required' => true),
        'city' => array('type' => 'string', 'max_length' => 255, 'required' => true),
        'firstAddressLine' => array('type' => 'string', 'max_length' => 255, 'required' => true),
        'secondAddressLine' => array('type' => 'string', 'max_length' => 255, 'required' => false),
        'state' => array('type' => 'string', 'max_length' => 255, 'required' => false),
        'postalCode' => array('type' => 'string', 'max_length' => 255, 'required' => false)
    );

    /**
     * @var string
     */
    protected $countryCode;

    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $firstAddressLine;

    /**
     * @var string
     */
    protected $secondAddressLine;

    /**
     * @var string
     */
    protected $state;

    /**
     * @var string
     */
    protected $postalCode;

    /**
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getFirstAddressLine()
    {
        return $this->firstAddressLine;
    }

    /**
     * @param string $firstAddressLine
     */
    public function setFirstAddressLine($firstAddressLine)
    {
        $this->firstAddressLine = $firstAddressLine;
    }

    /**
     * @return string
     */
    public function getSecondAddressLine()
    {
        return $this->secondAddressLine;
    }

    /**
     * @param string $secondAddressLine
     */
    public function setSecondAddressLine($secondAddressLine)
    {
        $this->secondAddressLine = $secondAddressLine;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @param string $postalCode
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;
    }
    
}
}


namespace  Coinfide\Entity {

class AffiliateInfo extends Base
{
    protected $validationRules = array(
        'affiliateId' => array('type' => 'string', 'max_length' => 50, 'required' => true),
        'campaignId' => array('type' => 'string', 'max_length' => 50, 'required' => true),
        'bannerId' => array('type' => 'string', 'max_length' => 50, 'required' => true),
        'customParameters' => array('type' => 'string', 'max_length' => 255, 'required' => false)
    );

    /**
     * @var string
     */
    protected $affiliateId;

    /**
     * @var string
     */
    protected $campaignId;

    /**
     * @var string
     */
    protected $bannerId;

    /**
     * @var string
     */
    protected $customParameters;

    /**
     * @return string
     */
    public function getAffiliateId()
    {
        return $this->affiliateId;
    }

    /**
     * @param string $affiliateId
     */
    public function setAffiliateId($affiliateId)
    {
        $this->affiliateId = $affiliateId;
    }

    /**
     * @return string
     */
    public function getCampaignId()
    {
        return $this->campaignId;
    }

    /**
     * @param string $campaignId
     */
    public function setCampaignId($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * @return string
     */
    public function getBannerId()
    {
        return $this->bannerId;
    }

    /**
     * @param string $bannerId
     */
    public function setBannerId($bannerId)
    {
        $this->bannerId = $bannerId;
    }

    /**
     * @return string
     */
    public function getCustomParameters()
    {
        return $this->customParameters;
    }

    /**
     * @param string $customParameters
     */
    public function setCustomParameters($customParameters)
    {
        $this->customParameters = $customParameters;
    }
    
}
}


namespace  Coinfide\Entity {

class Callback extends Base
{
    protected $validationRules = array(
        'externalOrderId' => array('type' => 'string', 'required' => false),
        'uid' => array('type' => 'string', 'max_length' => 36, 'required' => true),
        'seller' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => true),
        'buyer' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => true),
        'amountTotal' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => true),
        'currencyCode' => array('type' => 'string', 'max_length' => 3, 'required' => true),
        'status' => array('type' => 'string', 'max_length' => 2, 'required' => true),
        'testOrder' => array('type' => 'boolean', 'required' => true),
        'merchantUrl' => array('type' => 'string', 'required' => true),
        'transactions' => array('type' => 'list', 'prototype' => array('type' => 'object', 'class' => '\Coinfide\Entity\Transaction', 'required' => false), 'required' => false),
    );

    /**
     * @var string
     */
    protected $externalOrderId;

    /**
     * @var string
     */
    protected $uid;

    /**
     * @var Account
     */
    protected $seller;

    /**
     * @var Account
     */
    protected $buyer;

    /**
     * @var float
     */
    protected $amountTotal;

    /**
     * @var string
     */
    protected $currencyCode;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var boolean
     */
    protected $testOrder;

    /**
     * @var string
     */
    protected $merchantUrl;

    /**
     * @var Transaction[]
     */
    protected $transactions;

    /**
     * @return string
     */
    public function getExternalOrderId()
    {
        return $this->externalOrderId;
    }

    /**
     * @param string $externalOrderId
     */
    public function setExternalOrderId($externalOrderId)
    {
        $this->externalOrderId = $externalOrderId;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return Account
     */
    public function getSeller()
    {
        return $this->seller;
    }

    /**
     * @param Account $seller
     */
    public function setSeller($seller)
    {
        $this->seller = $seller;
    }

    /**
     * @return Account
     */
    public function getBuyer()
    {
        return $this->buyer;
    }

    /**
     * @param Account $buyer
     */
    public function setBuyer($buyer)
    {
        $this->buyer = $buyer;
    }

    /**
     * @return float
     */
    public function getAmountTotal()
    {
        return $this->amountTotal;
    }

    /**
     * @param float $amountTotal
     */
    public function setAmountTotal($amountTotal)
    {
        $this->amountTotal = $amountTotal;
    }

    /**
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * @param string $currencyCode
     */
    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return boolean
     */
    public function getTestOrder()
    {
        return $this->testOrder;
    }

    /**
     * @param boolean $testOrder
     */
    public function setTestOrder($testOrder)
    {
        $this->testOrder = $testOrder;
    }

    /**
     * @return string
     */
    public function getMerchantUrl()
    {
        return $this->merchantUrl;
    }

    /**
     * @param string $merchantUrl
     */
    public function setMerchantUrl($merchantUrl)
    {
        $this->merchantUrl = $merchantUrl;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions()
    {
        return $this->transactions;
    }

    /**
     * @param Transaction[] $transactions
     */
    public function setTransactions($transactions)
    {
        $this->transactions = $transactions;
    }

}
}


namespace  Coinfide\Entity {

class Order extends Base
{
    protected $validationRules = array(
        'uid' => array('type' => 'string', 'max_length' => 36, 'required' => false),
        'status' => array('type' => 'string', 'max_length' => 2, 'required' => false),
        'seller' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => true),
        'buyer' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => true),
        'currencyCode' => array('type' => 'string', 'max_length' => 3, 'required' => true),
        'discountAmount' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => false),
        'discountPercent' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => false),
        'amountTotal' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => false),
        'issueDate' => array('type' => 'date', 'required' => false),
        'dueDate' => array('type' => 'date', 'required' => false),
        'externalOrderId' => array('type' => 'string', 'required' => false),
        'reference' => array('type' => 'string', 'required' => false),
        'note' => array('type' => 'string', 'required' => false),
        'terms' => array('type' => 'string', 'required' => false),
        'provisionChannel' => array('type' => 'string', 'max_length' => 6, 'required' => false),
        'affiliateInfo' => array('type' => 'object', 'class' => '\Coinfide\Entity\AffiliateInfo', 'required' => false),
        'acceptPaymentsIfOrderExpired' => array('type' => 'boolean', 'required' => false),
        'taxBeforeDiscount' => array('type' => 'boolean', 'required' => false),
        'taxInclusive' => array('type' => 'boolean', 'required' => false),
        'paymentPageUrl' => array('type' => 'string', 'required' => false),
        'successUrl' => array('type' => 'string', 'required' => false),
        'failUrl' => array('type' => 'string', 'required' => false),
        'orderItems' => array('type' => 'list', 'prototype' => array('type' => 'object', 'class' => '\Coinfide\Entity\OrderItem', 'required' => false), 'required' => true, 'min_items' => 1),
        'shippingAddress' => array('type' => 'object', 'class' => '\Coinfide\Entity\Address', 'required' => false)
    );

    /**
     * @var string
     */
    protected $uid;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var Account
     */
    protected $seller;

    /**
     * @var Account
     */
    protected $buyer;

    /**
     * @var string
     */
    protected $currencyCode;

    /**
     * @var float
     */
    protected $discountAmount;

    /**
     * @var float
     */
    protected $discountPercent;

    /**
     * @var float
     */
    protected $amountTotal;

    /**
     * @var string
     */
    protected $issueDate;

    /**
     * @var string
     */
    protected $dueDate;

    /**
     * @var string
     */
    protected $externalOrderId;

    /**
     * @var string
     */
    protected $reference;

    /**
     * @var string
     */
    protected $note;

    /**
     * @var string
     */
    protected $terms;

    /**
     * @var string
     */
    protected $provisionChannel;

    /**
     * @var AffiliateInfo
     */
    protected $affiliateInfo;

    /**
     * @var bool
     */
    protected $acceptPaymentsIfOrderExpired;

    /**
     * @var bool
     */
    protected $taxBeforeDiscount;

    /**
     * @var bool
     */
    protected $taxInclusive;

    /**
     * @var string
     */
    protected $paymentPageUrl;

    /**
     * @var string
     */
    protected $successUrl;

    /**
     * @var string
     */
    protected $failUrl;

    /**
     * @var OrderItem[]
     */
    protected $orderItems;

    /**
     * @var Address
     */
    protected $shippingAddress;
    
    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return Account
     */
    public function getSeller()
    {
        return $this->seller;
    }

    /**
     * @param Account $seller
     */
    public function setSeller($seller)
    {
        $this->seller = $seller;
    }

    /**
     * @return Account
     */
    public function getBuyer()
    {
        return $this->buyer;
    }

    /**
     * @param Account $buyer
     */
    public function setBuyer($buyer)
    {
        $this->buyer = $buyer;
    }

    /**
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * @param string $currencyCode
     */
    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->discountAmount;
    }

    /**
     * @param float $discountAmount
     */
    public function setDiscountAmount($discountAmount)
    {
        $this->discountAmount = $discountAmount;
    }

    /**
     * @return float
     */
    public function getDiscountPercent()
    {
        return $this->discountPercent;
    }

    /**
     * @param float $discountPercent
     */
    public function setDiscountPercent($discountPercent)
    {
        $this->discountPercent = $discountPercent;
    }

    /**
     * @return float
     */
    public function getAmountTotal()
    {
        return $this->amountTotal;
    }

    /**
     * @param float $amountTotal
     */
    public function setAmountTotal($amountTotal)
    {
        $this->amountTotal = $amountTotal;
    }

    /**
     * @return string
     */
    public function getIssueDate()
    {
        return $this->issueDate;
    }

    /**
     * @param string $issueDate
     */
    public function setIssueDate($issueDate)
    {
        $this->issueDate = $issueDate;
    }

    /**
     * @return string
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param string $dueDate
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
    }

    /**
     * @return string
     */
    public function getExternalOrderId()
    {
        return $this->externalOrderId;
    }

    /**
     * @param string $externalOrderId
     */
    public function setExternalOrderId($externalOrderId)
    {
        $this->externalOrderId = $externalOrderId;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference($reference)
    {
        $this->reference = $reference;
    }

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @param string $note
     */
    public function setNote($note)
    {
        $this->note = $note;
    }

    /**
     * @return string
     */
    public function getTerms()
    {
        return $this->terms;
    }

    /**
     * @param string $terms
     */
    public function setTerms($terms)
    {
        $this->terms = $terms;
    }

    /**
     * @return string
     */
    public function getProvisionChannel()
    {
        return $this->provisionChannel;
    }

    /**
     * @param string $provisionChannel
     */
    public function setProvisionChannel($provisionChannel)
    {
        $this->provisionChannel = $provisionChannel;
    }

    /**
     * @return AffiliateInfo
     */
    public function getAffiliateInfo()
    {
        return $this->affiliateInfo;
    }

    /**
     * @param AffiliateInfo $affiliateInfo
     */
    public function setAffiliateInfo($affiliateInfo)
    {
        $this->affiliateInfo = $affiliateInfo;
    }

    /**
     * @return boolean
     */
    public function getAcceptPaymentsIfOrderExpired()
    {
        return $this->acceptPaymentsIfOrderExpired;
    }

    /**
     * @param boolean $acceptPaymentsIfOrderExpired
     */
    public function setAcceptPaymentsIfOrderExpired($acceptPaymentsIfOrderExpired)
    {
        $this->acceptPaymentsIfOrderExpired = $acceptPaymentsIfOrderExpired;
    }

    /**
     * @return boolean
     */
    public function getTaxBeforeDiscount()
    {
        return $this->taxBeforeDiscount;
    }

    /**
     * @param boolean $taxBeforeDiscount
     */
    public function setTaxBeforeDiscount($taxBeforeDiscount)
    {
        $this->taxBeforeDiscount = $taxBeforeDiscount;
    }

    /**
     * @return boolean
     */
    public function getTaxInclusive()
    {
        return $this->taxInclusive;
    }

    /**
     * @param boolean $taxInclusive
     */
    public function setTaxInclusive($taxInclusive)
    {
        $this->taxInclusive = $taxInclusive;
    }

    /**
     * @return string
     */
    public function getPaymentPageUrl()
    {
        return $this->paymentPageUrl;
    }

    /**
     * @param string $paymentPageUrl
     */
    public function setPaymentPageUrl($paymentPageUrl)
    {
        $this->paymentPageUrl = $paymentPageUrl;
    }

    /**
     * @return string
     */
    public function getSuccessUrl()
    {
        return $this->successUrl;
    }

    /**
     * @param string $successUrl
     */
    public function setSuccessUrl($successUrl)
    {
        $this->successUrl = $successUrl;
    }

    /**
     * @return string
     */
    public function getFailUrl()
    {
        return $this->failUrl;
    }

    /**
     * @param string $failUrl
     */
    public function setFailUrl($failUrl)
    {
        $this->failUrl = $failUrl;
    }

    /**
     * @return OrderItem[]
     */
    public function getOrderItems()
    {
        return $this->orderItems;
    }

    /**
     * @param OrderItem[] $orderItems
     */
    public function setOrderItems($orderItems)
    {
        $this->orderItems = $orderItems;
    }

    /**
     * @param OrderItem $orderItem
     */
    public function addOrderItem($orderItem)
    {
        $this->orderItems[] = $orderItem;
    }

    /**
     * @return Address
     */
    public function getShippingAddress()
    {
        return $this->shippingAddress;
    }

    /**
     * @param Address $shippingAddress
     */
    public function setShippingAddress($shippingAddress)
    {
        $this->shippingAddress = $shippingAddress;
    }
}
}


namespace  Coinfide\Entity {

use Coinfide\CoinfideException;
use Coinfide\Entity\Tax;

class OrderItem extends Base
{
    protected $validationRules = array(
        'type' => array('type' => 'string', 'required' => true),
        'name' => array('type' => 'string', 'max_length' => 255, 'required' => true),
        'description' => array('type' => 'string', 'max_length' => 255, 'required' => false),
        'priceUnit' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => true),
        'quantity' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => true),
        'discountAmount' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => false),
        'discountPercent' => array('type' => 'decimal', 'decimal' => 14, 'precision' => 2, 'required' => false),
        'tax' => array('type' => 'object', 'class' => '\Coinfide\Entity\Tax', 'required' => false)
    );

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var float
     */
    protected $priceUnit;

    /**
     * @var float
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $discountAmount;

    /**
     * @var float
     */
    protected $discountPercent;

    /**
     * @var Tax
     */
    protected $tax;

    public function validate()
    {
        if (!in_array($this->type, ['I', 'S'])) {
            throw new CoinfideException('Type of OrderItem must be one of "I" (item), "S" (shipping)');
        }

        parent::validate();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return float
     */
    public function getPriceUnit()
    {
        return $this->priceUnit;
    }

    /**
     * @param float $priceUnit
     */
    public function setPriceUnit($priceUnit)
    {
        $this->priceUnit = $priceUnit;
    }

    /**
     * @return float
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param float $quantity
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->discountAmount;
    }

    /**
     * @param float $discountAmount
     */
    public function setDiscountAmount($discountAmount)
    {
        $this->discountAmount = $discountAmount;
    }

    /**
     * @return float
     */
    public function getDiscountPercent()
    {
        return $this->discountPercent;
    }

    /**
     * @param float $discountPercent
     */
    public function setDiscountPercent($discountPercent)
    {
        $this->discountPercent = $discountPercent;
    }

    /**
     * @return \Coinfide\Entity\Tax
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * @param \Coinfide\Entity\Tax $tax
     */
    public function setTax($tax)
    {
        $this->tax = $tax;
    }
}
}


namespace  Coinfide\Entity {

class OrderList extends Base
{
    protected $validationRules = array(
        'orderList' => array('type' => 'list', 'prototype' => array('type' => 'object', 'class' => '\Coinfide\Entity\OrderShort', 'required' => false), 'required' => true, 'min_items' => 1),
    );

    /**
     * @var OrderShort[]
     */
    protected $orderList;

    /**
     * @return OrderShort[]
     */
    public function getOrderList()
    {
        return $this->orderList;
    }

    /**
     * @param OrderShort[] $orderList
     */
    public function setOrderList($orderList)
    {
        $this->orderList = $orderList;
    }
}
}


namespace  Coinfide\Entity {

class OrderShort extends Base
{
    protected $validationRules = array(
        'externalOrderId' => array('type' => 'string', 'required' => false),
        'uid' => array('type' => 'string', 'max_length' => 36, 'required' => true),
        'seller' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => true),
        'buyer' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => true),
        'amountTotal' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => true),
        'currencyCode' => array('type' => 'string', 'max_length' => 3, 'required' => true),
        'status' => array('type' => 'string', 'max_length' => 2, 'required' => true),
        'testOrder' => array('type' => 'boolean', 'required' => true),
        'merchantUrl' => array('type' => 'string', 'required' => false),
        'transactions' => array('type' => 'list', 'prototype' => array('type' => 'object', 'class' => '\Coinfide\Entity\Transaction', 'required' => false), 'required' => false, 'min_items' => 1)
    );

    /**
     * @var string
     */
    protected $externalOrderId;

    /**
     * @var string
     */
    protected $uid;

    /**
     * @var Account
     */
    protected $seller;

    /**
     * @var Account
     */
    protected $buyer;

    /**
     * @var float
     */
    protected $amountTotal;

    /**
     * @var string
     */
    protected $currencyCode;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var boolean
     */
    protected $testOrder;

    /**
     * @var string
     */
    protected $merchantUrl;

    /**
     * @var Transaction[]
     */
    protected $transactions;

    /**
     * @return string
     */
    public function getExternalOrderId()
    {
        return $this->externalOrderId;
    }

    /**
     * @param string $externalOrderId
     */
    public function setExternalOrderId($externalOrderId)
    {
        $this->externalOrderId = $externalOrderId;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return Account
     */
    public function getSeller()
    {
        return $this->seller;
    }

    /**
     * @param Account $seller
     */
    public function setSeller($seller)
    {
        $this->seller = $seller;
    }

    /**
     * @return Account
     */
    public function getBuyer()
    {
        return $this->buyer;
    }

    /**
     * @param Account $buyer
     */
    public function setBuyer($buyer)
    {
        $this->buyer = $buyer;
    }

    /**
     * @return float
     */
    public function getAmountTotal()
    {
        return $this->amountTotal;
    }

    /**
     * @param float $amountTotal
     */
    public function setAmountTotal($amountTotal)
    {
        $this->amountTotal = $amountTotal;
    }

    /**
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * @param string $currencyCode
     */
    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return boolean
     */
    public function getTestOrder()
    {
        return $this->testOrder;
    }

    /**
     * @param boolean $testOrder
     */
    public function setTestOrder($testOrder)
    {
        $this->testOrder = $testOrder;
    }

    /**
     * @return string
     */
    public function getMerchantUrl()
    {
        return $this->merchantUrl;
    }

    /**
     * @param string $merchantUrl
     */
    public function setMerchantUrl($merchantUrl)
    {
        $this->merchantUrl = $merchantUrl;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions()
    {
        return $this->transactions;
    }

    /**
     * @param Transaction[] $transactions
     */
    public function setTransactions($transactions)
    {
        $this->transactions = $transactions;
    }
}
}


namespace  Coinfide\Entity {

class OrderStatus extends Base
{
    protected $validationRules = array(
        'order' => array('type' => 'object', 'class' => '\Coinfide\Entity\Order', 'required' => true),
        'redirectUrl' => array('type' => 'string', 'required' => false)
    );

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var string
     */
    protected $redirectUrl;

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @param string $redirectUrl
     */
    public function setRedirectUrl($redirectUrl)
    {
        $this->redirectUrl = $redirectUrl;
    }

}
}


namespace  Coinfide\Entity {

class Parameter extends Base
{
    protected $validationRules = array(
        'name' => array('type' => 'string', 'required' => true),
        'value' => array('type' => 'string', 'required' => true)
    );

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $value;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

}
}


namespace  Coinfide\Entity {

use Coinfide\CoinfideException;

class Phone extends Base
{
    protected $validationRules = array(
        'countryCode' => array('type' => 'string', 'required' => false),
        'number' => array('type' => 'string', 'required' => false),
        'fullNumber' => array('type' => 'string', 'required' => false)
    );



    /**
     * @var string
     */
    protected $countryCode;

    /**
     * @var string
     */
    protected $number;

    /**
     * @var string
     */
    protected $fullNumber;

    public function validate()
    {
        parent::validate();

        if (!($this->fullNumber || ($this->number && $this->countryCode))) {
            throw new CoinfideException(sprintf('Please set either fullNumber or number AND countryCode for Phone object'));
        }
    }

    /**
     * @return string
     */
    public function getFullNumber()
    {
        return $this->fullNumber;
    }

    /**
     * @param string $fullNumber
     */
    public function setFullNumber($fullNumber)
    {
        $this->fullNumber = $fullNumber;
    }

    /**
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param string $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }
}
}


namespace  Coinfide\Entity {

class Tax extends Base
{
    protected $validationRules = array(
        'name' => array('type' => 'string', 'max_length' => 255, 'required' => true),
        'rate' => array('type' => 'decimal', 'digits' => 3, 'precision' => 3, 'required' => true)
    );

    /**
     * @var string
     */
    protected $name;

    /**
     * @var float
     */
    protected $rate;

    /**
     * @return float
     */
    public function getRate()
    {
        return $this->rate;
    }

    /**
     * @param float $rate
     */
    public function setRate($rate)
    {
        $this->rate = $rate;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
}


namespace  Coinfide\Entity {

class Token extends Base
{
    protected $validationRules = array(
        'accessToken' => array('type' => 'string', 'required' => true),
        'expiresIn' => array('type' => 'integer', 'required' => true),
        'refreshToken' => array('type' => 'string', 'required' => true),
    );

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var string
     */
    protected $expiresIn;

    /**
     * @var string
     */
    protected $refreshToken;

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return integer
     */
    public function getExpiresIn()
    {
        return $this->expiresIn;
    }

    /**
     * @param string $expiresIn
     */
    public function setExpiresIn($expiresIn)
    {
        $this->expiresIn = $expiresIn;
    }

    /**
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * @param string $refreshToken
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }
    
    
}
}


namespace  Coinfide\Entity {

class Transaction extends Base
{
    protected $validationRules = array(
        'uid' => array('type' => 'string', 'max_length' => 10, 'required' => true),
        'type' => array('type' => 'string', 'max_length' => 1, 'required' => true),
        'action' => array('type' => 'string', 'max_length' => 2, 'required' => true),
        'status' => array('type' => 'string', 'max_length' => 1, 'required' => true),
        'amount' => array('type' => 'decimal', 'digits' => 14, 'precision' => 2, 'required' => true),
        'paymentMethodCode' => array('type' => 'string', 'max_length' => 100, 'required' => false),
        'payer' => array('type' => 'object', 'class' => '\Coinfide\Entity\Account', 'required' => false),
        'parameters' => array('type' => 'list', 'prototype' => array('type' => 'object', 'class' => '\Coinfide\Entity\Parameter', 'required' => false), 'required' => false),
    );

    /**
     * @var string
     */
    protected $uid;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    protected $paymentMethodCode;

    /**
     * @var Account
     */
    protected $payer;

    /**
     * @var Parameter[]
     */
    protected $parameters;

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid($uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return $this->paymentMethodCode;
    }

    /**
     * @param string $paymentMethodCode
     */
    public function setPaymentMethodCode($paymentMethodCode)
    {
        $this->paymentMethodCode = $paymentMethodCode;
    }

    /**
     * @return Account
     */
    public function getPayer()
    {
        return $this->payer;
    }

    /**
     * @param Account $payer
     */
    public function setPayer($payer)
    {
        $this->payer = $payer;
    }

    /**
     * @return Parameter[]
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param Parameter[] $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }
    
}
}


namespace  Coinfide\Entity {

class WrappedOrder extends Base
{
    protected $validationRules = array(
        'order' => array('type' => 'object', 'class' => '\Coinfide\Entity\Order', 'required' => true),
        'redirectUrl' => array('type' => 'string', 'required' => true)
    );

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var string
     */
    protected $redirectUrl;

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @param string $redirectUrl
     */
    public function setRedirectUrl($redirectUrl)
    {
        $this->redirectUrl = $redirectUrl;
    }


}
}


namespace  Coinfide {

use Coinfide\Entity\Order;
use Coinfide\Entity\OrderList;
use Coinfide\Entity\OrderStatus;
use Coinfide\Entity\Token;
use Coinfide\Entity\WrappedOrder;

class Client
{
    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var Token
     */
    protected $token;

    /**
     * @var integer
     */
    protected $tokenFetchTime;

    /**
     * @var array
     */
    protected $options;

    public function __construct($options = array())
    {
        $this->options = array_merge(array(
            'trace' => false,
            'sslOptions' => array()
        ), $options);
    }

    public function setMode($mode)
    {
        if ($mode == 'demo') {
            $this->endpoint = 'http://demo-api.enauda.com/paymentapi/';
        } elseif ($mode == 'prod') {
            $this->endpoint = 'https://paymentapi.coinfide.com/paymentapi/';
        } else {
            throw new CoinfideException(sprintf('Client mode "%s" unknown', $mode));
        }
    }

    public function setCredentials($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function orderStatus($uid, $status)
    {
        $token = $this->getToken();

        $statuses = array('SE', 'DE', 'MP', 'CA');

        if (!in_array($status, $statuses)) {
            throw new \Exception(sprintf('New order status must be one of "%s"', $statuses));
        }

        $response = $this->request('order/status', array('uid' => $uid, 'status' => $status), $token->getAccessToken());

        $orderList = new OrderStatus();

        $orderList->fromArray($response);
        $orderList->validate();

        return $orderList;
    }

    public function orderDetailsByUid($uid)
    {
        $token = $this->getToken();

        $params = array('uid' => $uid);

        $response = $this->request('order/details', $params, $token->getAccessToken());

        $orderList = new OrderList();

        $orderList->fromArray($response);
        $orderList->validate();

        return $orderList;
    }

    public function orderDetailsByExternalOrderId($externalOrderId)
    {
        $token = $this->getToken();

        $params = array('externalOrderId' => $externalOrderId);

        $response = $this->request('order/details', $params, $token->getAccessToken());

        $orderList = new OrderList();

        $orderList->fromArray($response);
        $orderList->validate();

        return $orderList;
    }

    public function orderList($dateFrom, $dateTo)
    {
        $token = $this->getToken();

        $response = $this->request('order/list', array('dateFrom'=> $dateFrom, 'dateTo' => $dateTo), $token->getAccessToken());

        $orderList = new OrderList();

        $orderList->fromArray($response);
        $orderList->validate();

        return $orderList;
    }

    public function getToken()
    {
        if (!$this->token || $this->token->getExpiresIn() + $this->tokenFetchTime < time()) {
            //fetch new token. Do not refresh (yet) since PHP follows request-reponse model and does not have any
            //persistent storage by default
            if (!$this->username || !$this->password) {
                throw new CoinfideException('Please call "setCredentials" and provide your credentials');
            }

            $response = $this->request('auth/token', array('username' => $this->username, 'password' => $this->password));

            $token = new Token();
            $token->fromArray($response);

            return $this->token = $token;
        }

        return $this->token;
    }

    public function submitOrder(Order $order)
    {
        $token = $this->getToken();

        $response = $this->request('order/create', array('order' => $order->toArray()), $token->getAccessToken());

        $wrappedOrder = new WrappedOrder();

        $wrappedOrder->fromArray($response);
        $wrappedOrder->validate();

        return $wrappedOrder;
    }

    public function refund($orderId, $amount)
    {
        $token = $this->getToken();

        $response = $this->request(
            'order/refund',
            array('orderId' => $orderId, 'amount' => $amount),
            $token->getAccessToken()
        );

        $order = new Order();
        $order->fromArray($response['order']);
        $order->validate();

        return $order;
    }

    public function request($path, $data, $token = '')
    {
        if (!$this->endpoint) {
            throw new CoinfideException('No endpoint set, call "setMode" first');
        }

        if ($this->options['trace']) {
            print '--> DEBUG PATH '.$path.PHP_EOL;
            print '--> DEBUG SENT JSON START'.PHP_EOL;
            print json_encode($data, JSON_PRETTY_PRINT).PHP_EOL;
            print '--> DEBUG SENT JSON END'.PHP_EOL;
        }

        $curl = curl_init($this->endpoint . $path);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt_array($curl, $this->options['sslOptions']);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(sprintf('Authorization: Basic %s', $token), 'Content-Type: application/json'));

        $result = curl_exec($curl);

        $error = curl_errno($curl);

        if ($error) {
            throw new CoinfideException(sprintf('CURL error %d: %s', $error, curl_error($curl)));
        }

        if ($this->options['trace']) {
            print '--> DEBUG RECEIVED RESULT START'.PHP_EOL;
            print $result.PHP_EOL;
            print '--> DEBUG RECEIVED RESULT END'.PHP_EOL;
        }

        $decoded = json_decode($result, true);
        
        if ($decoded === null) {
            throw new CoinfideException('Received JSON is not decodable');
        }

        if ($this->options['trace']) {
            print '--> DEBUG RECEIVED JSON START'.PHP_EOL;
            print json_encode($decoded, JSON_PRETTY_PRINT).PHP_EOL;
            print '--> DEBUG RECEIVED JSON END'.PHP_EOL;
        }

        if (isset($decoded['errorData'])) {
            throw new CoinfideException($decoded['errorData']['errorMessage'], $decoded['errorData']['errorCode']);
        }

        return $decoded;
    }
}
}
