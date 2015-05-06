/**
  * Data
  *
  * Data represents what the system *IS*
  */
object Data {

  /**
    * Account is an object that keeps history of its own transactions
    */
  class Account {
    import scala.collection.mutable._

    val ledgers = new MutableList[Double]()

    def currentBalance() = {
      ledgers.reduceLeft({_ + _})
    }

    def addLedger(ledger: Double) = {
      ledgers += ledger
    }
  }

}


/**
  * Context
  *
  * Context represents what the system *does*.
  */
object Context {

  /**
    * TransferMoney object represents the use-case of a Transfer Money.
    * Should be obvious, I hope :).
    */
  object TransferMoney {

    ////////////////////////////////////////////////////////////////////////
    //
    // As with Transfer Money in the real life, there are two important
    // roles involved in this use: the Money Source and the Money
    // Destination.
    // The algorithm, then, is simply transfer a specified amount of money
    // from object that plays Money Source role to the object that plays
    // the Money Destination role.
    //

    ////////////////////
    //
    // ROLE: MoneySource
    //

    trait MoneySourceRoleContract {
      def availableBalance(): Double
      def decreaseBalance(amount: Double)
    }

    trait MoneySourceRoleMethods extends MoneySourceRoleContract {
      def sendTransfer(destination: MoneyDestionationRoleMethods,
                       amount: Double) = {
        if (this.availableBalance() >= amount) {
          this.decreaseBalance(amount)
          destination.receiveTransfer(amount)
        }
      }
    }


    /////////////////////////
    //
    // ROLE: MoneyDestination
    //

    trait MoneyDestinationRoleContract {
      def increaseBalance(amount: Double)
    }

    trait MoneyDestionationRoleMethods extends MoneyDestinationRoleContract {
      def receiveTransfer(amount: Double) = {
        this.increaseBalance(amount)
      }
    }

  }



  /**
    * The main context object.
    * This object represent this context's API.
    */
  class TransferMoney(
    val source      : Data.Account,
    val destination : Data.Account,
    val amount      : Double) {

    import TransferMoney._

    ////////////////////////////////////////////////////////////////////////////
    //
    // The two implicit classes below basically fulfills the `TransferMoney`
    // role requirements for Account objects.
    //

    // Implicitly "converts" any Account object that plays the role of
    // MoneySource so it can fullfil its requirements.
    implicit class MoneySourceAccount(val account: Data.Account)
        extends MoneySourceRoleMethods {
      def availableBalance(): Double = account.currentBalance()
      def decreaseBalance(amount: Double) = account.addLedger(-amount)
    }

    // Implicitly "converts" any Account object that plays the role of
    // MoneyDestination so it can fullfil its requirements.
    implicit class MoneyDestionationAccount(val account: Data.Account)
        extends MoneyDestionationRoleMethods {
      def increaseBalance(amount: Double) = account.addLedger(amount)
    }

    def execute() = {
      // The important thing happened here:
      // `source` is an object of `Data.Account`, yet `Data.Account`
      // doesn't implement method called `sendTransfer`. The `sendTransfer`
      // is enabled by the existance of typeclass above, that will make
      // any `Data.Object` instance to play MoneySource role by fulfulling
      // `MoneySourceRoleContract`.
      source.sendTransfer(destination, amount)
    }

  }
}


/**
  * Application
  *
  * Application will use both the Data and Context
  */
object TransferMoneyApp extends App {

  import Data._
  import Context._

  val account1 = new Account()
  val account2 = new Account()

  account1.addLedger(1000)
  account2.addLedger( 100)

  println("Before: ")
  println(account1.currentBalance())
  println(account2.currentBalance())

  val transferMoney = new TransferMoney(
    account1, // source
    account2, // destination
    200)      // amount

  transferMoney.execute()

  println("After: ")
  println(account1.currentBalance())
  println(account2.currentBalance())
}

TransferMoneyApp.main(null)
