<?php

/*
 * This file is part of the PaymentSuite package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

namespace PaymentSuite\RedsysApiBundle\Services;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Elcodi\Component\Core\Services\ObjectDirector;
use PaymentSuite\PaymentCoreBundle\Exception\PaymentException;
use PaymentSuite\PaymentCoreBundle\PaymentMethodInterface;
use PaymentSuite\PaymentCoreBundle\Services\Interfaces\PaymentBridgeInterface;
use PaymentSuite\PaymentCoreBundle\Services\PaymentEventDispatcher;
use PaymentSuite\RedsysApiBundle\Entity\Transaction;
use PaymentSuite\RedsysApiBundle\RedsysApiMethod;

/**
 * RedsysApi manager
 *
 * List of response codes found in element <Ds_Response />
 * from a tansaction response <RETORNOXML />
 *
 *   101  Tarjeta caducada
 *   102  Tarjeta en excepción transitoria o bajo sospecha de fraude
 *   106  Intentos de PIN excedidos
 *   125  Tarjeta no efectiva
 *   129  Código de seguridad (CVV2/CVC2) incorrecto
 *   180  Tarjeta ajena al servicio
 *   184  Error en la autenticación del titular
 *   190  Denegación sin especificar Motivo
 *   191  Fecha de caducidad errónea
 *   202  Tarjeta en excepción transitoria o bajo sospecha de fraude con retirada de tarjeta
 *   904  Comercio no registrado en FUC
 *   909  Error de sistema
 *   912  Emisor no disponible
 *   913  Pedido repetido
 *   944  Sesión Incorrecta
 *   950  Operación de devolución no permitida
 *   9064 Número de posiciones de la tarjeta incorrecto
 *   9078 No existe método de pago válido para esa tarjeta
 *   9093 Tarjeta no existente
 *   9094 Rechazo servidores internacionales
 *   9104 Comercio con “titular seguro” y titular sin clave de compra segura
 *   9218 El comercio no permite operaciones seguras por entrada /operaciones
 *   9253 Tarjeta no cumple el check-digit
 *   9256 El comercio no puede realizar preautorizaciones
 *   9257 Esta tarjeta no permite operativa de preautorizaciones
 *   9261 Operación detenida por superar el control de restricciones en la entrada al SIS
 *   9912 Emisor no disponible
 *   9913 Error en la confirmación que el comercio envía al TPV Virtual
 *        (solo aplicable en la opción de sincronización SOAP)
 *   9914 Confirmación “KO” del comercio (solo aplicable en la opción de sincronización SOAP)
 *   9915 A petición del usuario se ha cancelado el pago
 *   9928 Anulación de autorización en diferido realizada por el SIS (proceso batch)
 *   9929 Anulación de autorización en diferido realizada por el comercio
 *   9997 Se está procesando otra transacción en SIS con la misma tarjeta
 *   9998 Operación en proceso de solicitud de datos de la tarjeta.
 *        El sistema queda a la espera de que el titular inserte la tarjeta, la operación no se procesa
 *   9999 Operación que ha sido redirigida al emisor a autenticar
 *
 * You can use the following credit card for testing: 4548812049400004 cvc: 123 expiration: <YYMM>
 *
 */
class RedsysApiManager
{
    /**
     * @var PaymentEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var PaymentBridgeInterface
     */
    protected $paymentBridge;

    /**
     * @var ObjectManager
     */
    protected $transactionObjectManager;

    /**
     * @var ObjectRepository
     */
    protected $transactionRepository;

    /**
     * @var array
     */
    protected $errors = array(
        "SIS0007" => "Error al desmontar el XML de entrada",
        "SIS0008" => "Error falta Ds_Merchant_MerchantCode",
        "SIS0009" => "Error de formato en Ds_Merchant_MerchantCode",
        "SIS0010" => "Error falta Ds_Merchant_Terminal",
        "SIS0011" => "Error de formato en Ds_Merchant_Terminal",
        "SIS0014" => "Error de formato en Ds_Merchant_Order",
        "SIS0015" => "Error falta Ds_Merchant_Currency",
        "SIS0016" => "Error de formato en Ds_Merchant_Currency",
        "SIS0018" => "Error falta Ds_Merchant_Amount",
        "SIS0019" => "Error de formato en Ds_Merchant_Amount",
        "SIS0020" => "Error falta Ds_Merchant_MerchantSignature",
        "SIS0021" => "Error la Ds_Merchant_MerchantSignature viene vacía",
        "SIS0022" => "Error de formato en Ds_Merchant_TransactionType",
        "SIS0023" => "Error Ds_Merchant_TransactionType desconocido",
        "SIS0026" => "Error No existe el comercio / terminal enviado",
        "SIS0027" => "Error Moneda enviada por el comercio es diferente a la que tiene asignada para ese terminal",
        "SIS0028" => "Error Comercio / terminal está dado de baja",
        "SIS0030" => "Error en un pago con tarjeta ha llegado un tipo de operación no valido",
        "SIS0031" => "Método de pago no definido",
        "SIS0034" => "Error de acceso a la Base de Datos",
        "SIS0038" => "Error en java",
        "SIS0040" => "Error el comercio / terminal no tiene ningún método de pago asignado",
        "SIS0041" => "Error en el cálculo de la firma de datos del comercio",
        "SIS0042" => "La firma enviada no es correcta",
        "SIS0046" => "El BIN de la tarjeta no está dado de alta",
        "SIS0051" => "Error número de pedido repetido",
        "SIS0054" => "Error no existe operación sobre la que realizar la devolución",
        "SIS0055" => "Error no existe más de un pago con el mismo número de pedido",
        "SIS0056" => "La operación sobre la que se desea devolver no está autorizada",
        "SIS0057" => "El importe a devolver supera el permitido",
        "SIS0058" => "Inconsistencia de datos, en la validación de una confirmación",
        "SIS0059" => "Error no existe operación sobre la que realizar la devolución",
        "SIS0060" => "Ya existe una confirmación asociada a la preautorización",
        "SIS0061" => "La preautorización sobre la que se desea confirmar no está autorizada",
        "SIS0062" => "El importe a confirmar supera el permitido",
        "SIS0063" => "Error. Número de tarjeta no disponible",
        "SIS0064" => "Error. El número de tarjeta no puede tener más de 19 posiciones",
        "SIS0065" => "Error. El número de tarjeta no es numérico",
        "SIS0066" => "Error. Mes de caducidad no disponible",
        "SIS0067" => "Error. El mes de la caducidad no es numérico",
        "SIS0068" => "Error. El mes de la caducidad no es válido",
        "SIS0069" => "Error. Año de caducidad no disponible",
        "SIS0070" => "Error. El Año de la caducidad no es numérico",
        "SIS0071" => "Tarjeta caducada",
        "SIS0072" => "Operación no anulable",
        "SIS0074" => "Error falta Ds_Merchant_Order",
        "SIS0075" => "Error el Ds_Merchant_Order tiene menos de 4 posiciones o más de 12",
        "SIS0076" => "Error el Ds_Merchant_Order no tiene las cuatro primeras posiciones numéricas",
        "SIS0078" => "Método de pago no disponible",
        "SIS0079" => "Error al realizar el pago con tarjeta",
        "SIS0081" => "La sesión es nueva, se han perdido los datos almacenados",
        "SIS0089" => "El valor de Ds_Merchant_ExpiryDate no ocupa 4 posiciones",
        "SIS0092" => "El valor de Ds_Merchant_ExpiryDate es nulo",
        "SIS0093" => "Tarjeta no encontrada en la tabla de rangos",
        "SIS0112" => "Error. El tipo de transacción especificado en Ds_Merchant_Transaction_Type no esta permitido",
        "SIS0115" => "Error no existe operación sobre la que realizar el pago de la cuota",
        "SIS0116" => "La operación sobre la que se desea pagar una cuota no es una operación válida",
        "SIS0117" => "La operación sobre la que se desea pagar una cuota no está autorizada",
        "SIS0118" => "Se ha excedido el importe total de las cuotas",
        "SIS0119" => "Valor del campo Ds_Merchant_DateFrecuency no válido",
        "SIS0120" => "Valor del campo Ds_Merchant_CargeExpiryDate no válido",
        "SIS0121" => "Valor del campo Ds_Merchant_SumTotal no válido",
        "SIS0122" => "Valor del campo Ds_merchant_DateFrecuency o Ds_Merchant_SumTotal tiene formato incorrecto",
        "SIS0123" => "Se ha excedido la fecha tope para realizar transacciones",
        "SIS0124" => "No ha transcurrido la frecuencia mínima en un pago recurrente sucesivo",
        "SIS0132" => "La fecha de Confirmación de Autorización no puede superar en más de 7 días a la de Preautorización",
        "SIS0139" => "Error el pago recurrente inicial está duplicado",
        "SIS0142" => "Tiempo excedido para el pago",
        "SIS0216" => "Error Ds_Merchant_CVV2 tiene mas de 3/4 posiciones",
        "SIS0217" => "Error de formato en Ds_Merchant_CVV2",
        "SIS0221" => "Error el CVV2 es obligatorio",
        "SIS0222" => "Ya existe una anulación asociada a la preautorización",
        "SIS0223" => "La preautorización que se desea anular no está autorizada",
        "SIS0225" => "Error no existe operación sobre la que realizar la anulación",
        "SIS0226" => "Inconsistencia de datos, en la validación de una anulación",
        "SIS0227" => "Valor del campo Ds_Merchan_TransactionDate no válido",
        "SIS0252" => "El comercio no permite el envío de tarjeta",
        "SIS0253" => "La tarjeta no cumple el check-digit",
        "SIS0261" => "Operación detenida por superar el control de restricciones en la entrada al TPV Virtual",
        "SIS0274" => "Tipo de operación desconocida o no permitida por esta entrada al TPV Virtual",
    );

    /**
     * Transaction types
     */
    const PAYMENT = 'payment';

    const REFUND = 'refund';

    /**
     * @var string
     *
     * Payment XML template message
     */
    const PAYMENT_MESSAGE = <<<'EOL'
<DATOSENTRADA>
    <DS_MERCHANT_AMOUNT>%s</DS_MERCHANT_AMOUNT>
    <DS_MERCHANT_ORDER>%s</DS_MERCHANT_ORDER>
    <DS_MERCHANT_MERCHANTCODE>%s</DS_MERCHANT_MERCHANTCODE>
    <DS_MERCHANT_CURRENCY>%s</DS_MERCHANT_CURRENCY>
    <DS_MERCHANT_PAN>%s</DS_MERCHANT_PAN>
    <DS_MERCHANT_CVV2>%s</DS_MERCHANT_CVV2>
    <DS_MERCHANT_TRANSACTIONTYPE>%s</DS_MERCHANT_TRANSACTIONTYPE>
    <DS_MERCHANT_TERMINAL>%s</DS_MERCHANT_TERMINAL>
    <DS_MERCHANT_EXPIRYDATE>%s</DS_MERCHANT_EXPIRYDATE>
    <DS_MERCHANT_MERCHANTSIGNATURE>%s</DS_MERCHANT_MERCHANTSIGNATURE>
</DATOSENTRADA>
EOL;

    /**
     * @var string
     *
     * Refund XML templete message
     */
    const REFUND_MESSAGE = <<<'EOL'
<DATOSENTRADA>
    <DS_MERCHANT_AMOUNT>%s</DS_MERCHANT_AMOUNT>
    <DS_MERCHANT_ORDER>%s</DS_MERCHANT_ORDER>
    <DS_MERCHANT_MERCHANTCODE>%s</DS_MERCHANT_MERCHANTCODE>
    <DS_MERCHANT_CURRENCY>%s</DS_MERCHANT_CURRENCY>
    <DS_MERCHANT_TRANSACTIONTYPE>%s</DS_MERCHANT_TRANSACTIONTYPE>
    <DS_MERCHANT_TERMINAL>%s</DS_MERCHANT_TERMINAL>
    <DS_MERCHANT_MERCHANTSIGNATURE>%s</DS_MERCHANT_MERCHANTSIGNATURE>
</DATOSENTRADA>
EOL;

    /**
     * @var string
     */
    private $number;

    /**
     * @var string
     */
    private $expiration;

    /**
     * @var string
     */
    private $cvc;

    /**
     * @var string
     *
     * Transaction type
     *
     * A – Ordinary payment
     * 1 – Preauthorization
     * 2 – Confirmation
     * 3 – Automatic refund
     * 5 – Recurring payments
     * 6 – Successive transaction
     * 9 – Preauthorization cancel
     * O – Deferred authorization
     * P - Deferred authorization confirm
     * Q - Deferred authorization cancel
     * R – Initial authorization recurring deferred
     * S – Authorization successive recurring deferred
     */
    private $transactionType;

    /**
     * @var string
     *
     * The response message
     */
    private $response;

    /**
     * @var string
     *
     * WSDL service endpoint
     */
    private $apiEndpoint;

    /**
     * @var string
     *
     * Merchant code
     */
    private $merchantCode;

    /**
     * @var string
     *
     * Merchant key
     */
    private $merchantSecretKey;

    /**
     * @var string
     *
     * Merchant terminal
     */
    private $merchantTerminal;

    /**
     * @var string
     *
     * Merchant currency
     *
     *   EUR: 978
     *   USD: 840
     *   GBP: 826
     *   JPY: 392
     */
    private $currency;

    /**
     * @param PaymentEventDispatcher $eventDispatcher
     * @param PaymentBridgeInterface $paymentBridge
     * @param ObjectDirector $transactionDirector
     * @param string $apiEndpoint
     * @param string $merchantCode
     * @param string $merchantSecretKey
     * @param string $merchantTerminal
     * @param string $currency
     */
    public function __construct(
        PaymentEventDispatcher $eventDispatcher,
        PaymentBridgeInterface $paymentBridge,
        ObjectManager $transactionObjectManager,
        ObjectRepository $transactionRepository,
        $apiEndpoint,
        $merchantCode,
        $merchantSecretKey,
        $merchantTerminal,
        $currency
    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->paymentBridge = $paymentBridge;
        $this->transactionObjectManager = $transactionObjectManager;
        $this->transactionRepository = $transactionRepository;
        $this->apiEndpoint = $apiEndpoint;
        $this->merchantCode = $merchantCode;
        $this->merchantSecretKey = $merchantSecretKey;
        $this->merchantTerminal = $merchantTerminal;
        $this->currency = $currency;

        /*
         * Old format, migrate
         */
        $this->transactionType = 'A';
    }

    /**
     * @param PaymentMethodInterface $method
     * @param integer $amount
     *
     * @throws PaymentException
     */
    public function processPayment(PaymentMethodInterface $method, $amount)
    {
        /**
         * @var RedsysApiMethod $method
         */
        $paymentData = array(
            'number' => $method->getCreditCartNumber(),
            'holder' => $method->getCreditCartOwner(),
            'expiration' => sprintf(
                '%s%s',
                substr($method->getCreditCartExpirationYear(), -2, 2),
                str_pad($method->getCreditCartExpirationMonth(), 2, '0', STR_PAD_LEFT)
            ),
            'cvc' => $method->getCreditCartSecurity(),
            'amount' => $amount
        );

        $this->setPayment($paymentData);

        try {

            $r = $this->_callSoap();

        } catch (\Exception $e) {
            /*
             * The Soap call failed
             */
            $this
                ->eventDispatcher
                ->notifyPaymentOrderFail(
                    $this->paymentBridge,
                    $method
                );

            throw new PaymentException($e->getMessage());
        }

        $this->storeTransaction($r);

        $this
            ->eventDispatcher
            ->notifyPaymentOrderLoad(
                $this->paymentBridge,
                $method
            );

        $this
            ->eventDispatcher
            ->notifyPaymentOrderCreated(
                $this->paymentBridge,
                $method
            );


        $this
            ->eventDispatcher
            ->notifyPaymentOrderDone(
                $this->paymentBridge,
                $method
            );

        if (!$this->isAuthorized($r)) {
            /**
             * The payment was not successful
             */
            $this
                ->eventDispatcher
                ->notifyPaymentOrderFail(
                    $this->paymentBridge,
                    $method
                );

            throw new PaymentException($this->getError($r));
        }

        /*
         * Everything is ok
         */
        $transaction = $this->getResponseData($r);

        $method
            ->setTransactionId($transaction['DS_AUTHORISATIONCODE'])
            ->setTransactionStatus('paid')
            ->setTransactionResponse($transaction);

        /**
         * Payment paid successfully
         *
         * Paid process has ended successfully
         */
        $this
            ->eventDispatcher
            ->notifyPaymentOrderSuccess(
                $this->paymentBridge,
                $method
            );

    }

    /**
     * @param $resource
     */
    protected function storeTransaction($resource)
    {
        $xml = is_object($resource) ? $resource->trataPeticionReturn : $resource;

        $transactionData = $this->getResponseData($resource);

        $returnCode =
            isset($transactionData['Ds_Response'])
                ? $transactionData['Ds_Response'] : "";

        $errorCode =
            isset($transactionData['CODIGO'])
                ? $transactionData['CODIGO'] : "";

        $authorizationCode =
            isset($transactionData['Ds_AuthorisationCode'])
                ? $transactionData['Ds_AuthorisationCode'] : "";

        /**
         * this is a RESPONSE for the moment
         */
        $transaction = new Transaction(
            $this->paymentBridge->getOrderId(),
            $this->paymentBridge->getAmount(),
            1,
            $returnCode,
            $errorCode,
            $authorizationCode,
            $xml
        );

        $this->transactionObjectManager->persist($transaction);
        $this->transactionObjectManager->flush($transaction);

    }

    /**
     * Get RedsysSoapHelper method name
     *
     * @return string Payment name
     */
    public function getPaymentName()
    {
        return 'RedsysWebservice';
    }

    /**
     * @param $paymentData
     */
    protected function setPayment($paymentData)
    {
        foreach ($paymentData as $field => $value) {
            $this->$field = $value;
        }

        $redsysUniqueTransactionId = $this->generateUniqueTransactionId();

        $this->response = sprintf(
            self::PAYMENT_MESSAGE,
            $this->paymentBridge->getAmount(),
            $redsysUniqueTransactionId,
            $this->merchantCode,
            $this->currency,
            $this->number,
            $this->cvc,
            $this->transactionType,
            $this->merchantTerminal,
            $this->expiration,
            $this->signTransaction($redsysUniqueTransactionId)
        );

    }

    /**
     * @param $orderData
     */
    protected function setRefund($orderData)
    {
        $this->transactionType = 3;

        foreach ($orderData as $field => $value) {
            $this->$field = $value;
        }

        $redsysUniqueTransactionId = $this->generateUniqueTransactionId();

        $this->response = sprintf(
            self::REFUND_MESSAGE,
            $this->paymentBridge->getAmount(),
            $redsysUniqueTransactionId,
            $this->merchantCode,
            $this->currency,
            $this->transactionType,
            $this->merchantTerminal,
            $this->signTransaction(
                $redsysUniqueTransactionId,
                self::REFUND
            )
        );

    }

    /**
     * @return mixed
     */
    protected function _callSoap()
    {
        $soapClient = New \SoapClient($this->apiEndpoint);

        $response = $soapClient->trataPeticion(
            array('datoEntrada' => $this->response)
        );

        return $response;

    }

    /**
     * Returns the transaction signature
     *
     * @param string $redsysUniqueTransactionId
     * @param string $transactionType
     *
     * @return string
     */
    protected function signTransaction($redsysUniqueTransactionId, $transactionType = self::PAYMENT)
    {
        $signature = "";

        if ($transactionType == self::PAYMENT) {

            $signature = sprintf(
                '%s%s%s%s%s%s%s%s',
                $this->paymentBridge->getAmount(),
                $redsysUniqueTransactionId,
                $this->merchantCode,
                $this->currency,
                $this->number,
                $this->cvc,
                $this->transactionType,
                $this->merchantSecretKey
            );

        } elseif ($transactionType == self::REFUND) {

            $signature = sprintf(
                '%s%s%s%s%s%s',
                $this->paymentBridge->getAmount(),
                $redsysUniqueTransactionId,
                $this->merchantCode,
                $this->currency,
                $this->transactionType,
                $this->merchantSecretKey
            );

        }

        return strtoupper(sha1($signature));
    }

    /**
     * @param $res
     *
     * @return bool
     */
    protected function isAuthorized($res)
    {
        $data = $this->getResponseData($res);

        if (!isset($data['DS_RESPONSE'])) {

            return false;
        }

        return $data['DS_RESPONSE'] < 100 || $data['DS_RESPONSE'] == 900;
    }

    /**
     * @param $res
     *
     * @return array
     */
    protected function getResponseData($res)
    {
        $res = is_object($res) ? $res->trataPeticionReturn : $res;

        $p = xml_parser_create();
        xml_parse_into_struct($p, $res, $responseData);

        $return = array();
        foreach ($responseData as $data) {
            if (!empty($data['value']) && $data['tag'] != 'DATOSENTRADA')
                $return[$data['tag']] = $data['value'];
        }

        return $return;
    }

    /**
     * @param $response
     *
     * @return bool
     */
    protected function getError($response)
    {
        if (!$this->isAuthorized($response)) {

            $data = $this->getResponseData($response);

            if (isset($data['CODIGO']))
                return $this->errors[$data['CODIGO']];
        }

        return false;
    }

    /**
     * @return string
     */
    protected function generateUniqueTransactionId()
    {
        $redsysUniqueTransactionId = sprintf(
            '%s-%s',
            $this->paymentBridge->getOrderId(),
            substr(uniqid(), -4, 4)
        );
        return $redsysUniqueTransactionId;
    }

}
