<?php

////////////////////////////////////////////////////////////////////////////////
// Library

/**
 * A container class for DCI-related utility.
 */
class DCI {
    private static $dciMethodsVarName = "_dci_methods";

    /**
     * Attach dynamically-added methods on the $className to the specified
     * $object.
     *
     * TODO: Detect name collision.
     */
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

    /**
     * Remove dynamically-added methods that contained on $className from the
     * specified $object.
     *
     * TODO: Detect name collision.
     */
    public static function detachMethods($object, $className) {
        if (!property_exists($object, self::$dciMethodsVarName)) {
            return;
        }

        $obj = new $className;
        $reflection = new ReflectionClass($obj);
        foreach ($reflection->getMethods() as $reflectionMethod) {
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

    /**
     * Dynamically assert that methods specified on $interfaceName is satisfied
     * $object.
     */
    public static function assertContractFulfilled($object, $interfaceName) {
        $allMethodNames = self::getAllMethodNames($object);

        $interfaceReflection = new ReflectionClass($interfaceName);
        foreach($interfaceReflection->getMethods() as $reflectionMethod) {
            if (! in_array($reflectionMethod->name, $allMethodNames)) {
                throw new Exception("There's no method '{$reflectionMethod->name}'");
            }
        }
    }

    /**
     * Determine whether an $object has $methodName.
     */
    public static function isCallable($object, $methodName) {
        if (property_exists($object, self::$dciMethodsVarName)) {
            if (array_key_exists($methodName,
                                 $object->{self::$dciMethodsVarName})) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call dynamically-attached $methodName on $object with arguments $args.
     */
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
        throw new Exception("Call to undefined method Account::" . $methodName);
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
// Context

interface MoneySourceContract {
    function availableBalance();
    function decreaseBalance($amount);
}

class MoneySourceRoleMethods {
    public function sendTransfer($moneyDestination, $amount) {
        if ($this->availableBalance() >= $amount) {
            $this->decreaseBalance($amount);
            $moneyDestination->receiveTransfer($amount);
        }
    }
}

interface MoneyDestinationContract {
    function increaseBalance($amount);
}

class MoneyDestinationRoleMethods {
    public function receiveTransfer($amount) {
        $this->increaseBalance($amount);
    }
}

/**
 * A operation supported by the system: Transferring Money.
 *
 * Please note that although this class is one of the most important class,
 * its object has to be instantiated inside another class.
 *
 * Please see `TransferMoneyWrapper` for more explanation.
 */
class TransferMoneyOperation {
    private $roles = array();

    protected static $sourceRoleName = "MONEY_SOURCE";
    protected static $destinationRoleName = "MONEY_DESTINATION";

    protected function addRole($name, $object) {
        $this->roles[$name] = $object;
    }

    protected function doExecute($amount) {
        $this->setUp();

        $source = $this->roles[self::$sourceRoleName];
        $destination = $this->roles[self::$destinationRoleName];
        $source->sendTransfer($destination, $amount);

        $this->tearDown();
    }

    private function setUp() {
        // Make sure all Roles has players
        $source = $this->roles[self::$sourceRoleName];
        if (! $source) {
            throw new Exception("No object plays the Source role");
        }
        $destination = $this->roles[self::$destinationRoleName];
        if (! $destination) {
            throw new Exception("No object plays the Destination role");
        }

        // Make sure the contract for both $source and $destination are
        // fullfilled. Otherwise, throw Exceptions.
        DCI::assertContractFulfilled($source, "MoneySourceContract");
        DCI::assertContractFulfilled($destination, "MoneyDestinationContract");

        // If both $source and $destination fullfills the contract, attach the
        // appropriate methods on them.
        DCI::attachMethods($source, "MoneySourceRoleMethods");
        DCI::attachMethods($destination, "MoneyDestinationRoleMethods");
    }

    private function tearDown() {
        $source = $this->roles[self::$sourceRoleName];
        $destination = $this->roles[self::$destinationRoleName];

        DCI::detachMethods($source, "MoneySourceRoleMethods");
        DCI::detachMethods($destination, "MoneyDestinationRoleMethods");
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

/**
 * The wrapper for the `TransferMoneyOperation`.
 *
 * This class is needed because we need to make sure that the dynamically-
 * attached method doesn't "escape" out of Context.
 *
 * In a more technical and concrete way: This class is needed because the only
 * scoping PHP supported is function call. We need that scoping mechanism to
 * detach the dynamically-attached methods. In this case, by making sure that
 * the `__destruct` is called. The way we can make sure the `__destruct` is
 * called is by constructing the object of this calls in a single function and
 * carefully *not* letting its reference escape outside of the function scope.
 *
 * Please see `TransferMoney::execute` for the usage example.
 *
 * If PHP has the ability to make a class inner or private, this class would be
 * a really good candidate.
 */
class TransferMoneyWrapper extends TransferMoneyOperation {
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

        parent::addRole(parent::$sourceRoleName, $this->source);
        parent::addRole(parent::$destinationRoleName, $this->destination);
    }

    public function __destruct() {
        DCI::detachMethods($this->source, "MoneySourceAccount");
        DCI::detachMethods($this->destination, "MoneyDestinationAccount");
    }

    public function execute() {
        $this->doExecute($this->amount);
    }
}

/**
 * The public API of the TransferMoney operation.
 */
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
    }

    public function execute() {
        $transferMoney = new TransferMoneyWrapper($this->source,
                                                  $this->destination,
                                                  $this->amount);
        $transferMoney->execute();
    }
}


////////////////////////////////////////////////////////////////////////////////
// Application

$account1 = new Account();
$account2 = new Account();

$account1->appendLedgerEntry(1000);
$account2->appendLedgerEntry( 100);

echo "Before: \n";
echo "account1: " . $account1->currentBalance() . "\n";
echo "account2: " . $account2->currentBalance() . "\n";

$transferMoney = new TransferMoney($account1, $account2, 200);
$transferMoney->execute();

echo "After: \n";
echo "account1: " . $account1->currentBalance() . "\n";
echo "account2: " . $account2->currentBalance() . "\n";

?>