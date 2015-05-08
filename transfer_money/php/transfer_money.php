<?php

////////////////////////////////////////////////////////////////////////////////
// Library

class DCI {
    private static $dciMethodsVarName = "_dci_methods";

    public static function attachMethods($object, $className) {
        if (!property_exists($object, self::$dciMethodsVarName)) {
            $object->{self::$dciMethodsVarName} = array();
        }

        $obj = new $className;
        $reflection = new ReflectionClass($obj);
        foreach ($reflection->getMethods() as $reflectionMethod) {
            $closure = $reflectionMethod->getClosure($obj);
            $object->{self::$dciMethodsVarName}[$reflectionMethod->name] =
                Closure::bind($closure, $object);
        }
    }

    public static function detachMethods($object, $className) {
        if (!property_exists($object, self::$dciMethodsVarName)) {
            return;
        }

        $obj = new $className;
        $reflection = new ReflectionClass($obj);
        foreach ($reflection->getMethods() as $reflectionMethod) {
            $closure = $reflectionMethod->getClosure($obj);
            unset($object->{self::$dciMethodsVarName}[$reflectionMethod->name]);
        }
    }

    private static function getAllMethodNames($object) {
        $methodNames = array();

        $objectReflection = new ReflectionObject($object);
        foreach($objectReflection->getMethods() as $reflectionMethod) {
            $methodNames[] = $reflectionMethod->name;
        }

        if (property_exists($object, self::$dciMethodsVarName)) {
            $methodNames = array_merge($methodNames, array_keys(
                $object->{self::$dciMethodsVarName}));
        }

        return $methodNames;
    }

    public static function assertContractFulfilled($object, $interfaceName) {
        $allMethodNames = self::getAllMethodNames($object);

        $interfaceReflection = new ReflectionClass($interfaceName);
        foreach($interfaceReflection->getMethods() as $reflectionMethod) {
            if (!in_array($reflectionMethod->name, $allMethodNames)) {
                throw new Exception("There's no method '{$reflectionMethod->name}'");
            }
        }
    }

    public static function isCallable($object, $methodName) {
        if (property_exists($object, self::$dciMethodsVarName)) {
            if (array_key_exists($methodName,
                                 $object->{self::$dciMethodsVarName})) {
                return true;
            }
        }

        return false;
    }

    public static function callMethod($object, $methodName, $args) {
        return call_user_func_array(
            $object->{self::$dciMethodsVarName}[$methodName], $args);
    }
}


////////////////////////////////////////////////////////////////////////////////
// Data

class Account {
    private $ledgerEntries = [];

    // Define magic methods that will be used by DCI.
    // TODO: Find a more non-intrusive way to do this.
    function __call($methodName, $args) {
        if (DCI::isCallable($this, $methodName)) {
            return DCI::callMethod($this, $methodName, $args);
        }
    }

    public function appendLedgerEntry($newEntry) {
        $this->ledgerEntries[] = $newEntry;
    }

    public function currentBalance() {
        $currentBalance = 0;

        foreach ($this->ledgerEntries as $ledgerEntry) {
            $currentBalance += $ledgerEntry;
        }

        return $currentBalance;
    }

}

////////////////////////////////////////////////////////////////////////////////
// Context and Interaction

interface MoneySourceContract {
    function availableBalance();
    function decreaseBalance($amount);
}

interface MoneyDestinationContract {
    function increaseBalance($amount);
}

class MoneySourceRoleMethods {
    public function sendTransfer($moneySink, $amount) {
        if ($this->availableBalance() >= $amount) {
            $this->decreaseBalance($amount);
            $moneySink->receiveTransfer($amount);
        }
    }
}

class MoneyDestinationRoleMethods {
    public function receiveTransfer($amount) {
        $this->increaseBalance($amount);
    }
}

class MoneySourceAccount {
    public function availableBalance() {
        return $this->currentBalance();
    }

    public function decreaseBalance($amount) {
        $this->appendLedgerEntry(-$amount);
    }
}

class MoneyDestinationAccount {
    public function increaseBalance($amount) {
        $this->appendLedgerEntry($amount);
    }
}


class TransferMoney {
    private $source;
    private $destination;
    private $amount;

    public function __construct(Account $source,
                                Account $destination,
                                $amount) {
        $this->source      = $source;
        $this->destination = $destination;
        $this->amount      = $amount;

        DCI::attachMethods($this->source, "MoneySourceAccount");
        DCI::attachMethods($this->destination, "MoneyDestinationAccount");
    }

    public function execute() {
        $this->setUp();
        $this->source->sendTransfer($this->destination, $this->amount);
        $this->tearDown();
    }

    private function setUp() {
        // Make sure the contract for both $source and $destination are
        // fullfilled. Otherwise, throw Exceptions.
        DCI::assertContractFulfilled(
            $this->source, "MoneySourceContract");
        DCI::assertContractFulfilled(
            $this->destination, "MoneyDestinationContract");

        // If both $source and $destination fullfills the contract, attach the
        // appropriate methods on them.
        DCI::attachMethods($this->source, "MoneySourceRoleMethods");
        DCI::attachMethods($this->destination, "MoneyDestinationRoleMethods");
    }

    private function tearDown() {
        DCI::detachMethods($this->source, "MoneySourceRoleMethods");
        DCI::detachMethods($this->destination, "MoneyDestinationRoleMethods");
    }
}


////////////////////////////////////////////////////////////////////////////////
// Application

$account1 = new Account();
$account2 = new Account();

$account1->appendLedgerEntry(1000);
$account2->appendLedgerEntry(500);

echo "Before: \n";
echo "account1: " . $account1->currentBalance() . "\n";
echo "account2: " . $account2->currentBalance() . "\n";

$transferMoney = new TransferMoney($account1, $account2, 200);
$transferMoney->execute();

echo "After: \n";
echo "account1: " . $account1->currentBalance() . "\n";
echo "account2: " . $account2->currentBalance() . "\n";

?>