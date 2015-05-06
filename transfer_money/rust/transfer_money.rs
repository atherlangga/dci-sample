
////////////////////////////////////////////////////////////////////////////////
//
// DATA
// ----
// Data represents what the system IS.
//
mod data {

    // Account is an object that keeps a record for its transactions.
    pub struct Account {
        pub ledger: Vec<f32>,
    }

    impl Account {
        // Get the current balance of account.
        pub fn current_balance(&self) -> f32 {
            self.ledger
                .iter()
                .fold(0_f32, |a, b| a + b)
        }
    }

}

////////////////////////////////////////////////////////////////////////////////
//
// CONTEXT
// -------
// Context represents what the system DOES.
//
mod context {

    // CONTEXT: Transfer Money.
    // A specification of Money Transfer use case.
    pub mod transfer_money {

        // Declare that this use case will use and depend on Data.
        use data;


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

        // The contract/requirement below must be fulfilled for object that will
        // play the role of MoneySource.
        pub trait MoneySourceRoleRequirement {
            fn available_balance(&self) -> f32;
            fn decrease_balance(&mut self, amount: f32) -> ();
        }

        // The Rust's trait and impl below basically says that "for every object
        // that fulfilled the `MoneySourceRoleRequirement`, they will
        // automatically be given `send_transfer` method".
        pub trait MoneySourceRoleMethods: MoneySourceRoleRequirement {
            fn send_transfer(&mut self,
                             amount: f32,
                             sink: &mut MoneyDestinationRoleMethods) -> () {
                if self.available_balance() >= amount {
                    self.decrease_balance(amount);
                    sink.receive_transfer(amount);
                }
            }
        }
        impl<T> MoneySourceRoleMethods for T
            where T: MoneySourceRoleRequirement {}


        /////////////////////////
        //
        // ROLE: MoneyDestination
        //

        // As with above, this contract/requirement below must be fullfilled by
        // object that play the MoneyDestination role.
        pub trait MoneyDestinationRoleRequirement {
            fn increase_balance(&mut self, amount: f32) -> ();
        }

        // The Rust's trait and impl below basically says that "for every object
        // that fulfilled the `MoneyDestinationRoleRequirement`, they will
        // automatically be given `receive_transfer` method".
        pub trait MoneyDestinationRoleMethods: MoneyDestinationRoleRequirement {
            fn receive_transfer(&mut self, amount: f32) -> () {
                self.increase_balance(amount);
            }
        }
        impl<T> MoneyDestinationRoleMethods for T
            where T: MoneyDestinationRoleRequirement {}



        ////////////////////////////////////////////////////////////////////////
        //
        // The struct `TransferMoney` below can be considered as the "API" to
        // manage and execute the context.
        // The struct below also responsible to start the interaction between
        // the roles.
        //

        pub struct TransferMoney<'a> {
            source      : &'a mut data::Account,
            destination : &'a mut data::Account,
            amount      : f32,
        }

        impl<'a> TransferMoney<'a> {
            pub fn new(
                source      : &'a mut data::Account,
                destination : &'a mut data::Account,
                amount      : f32) -> TransferMoney<'a> {
                return TransferMoney {
                    source      : source,
                    destination : destination,
                    amount      : amount };
            }

            pub fn execute(&mut self) {
                self.source.send_transfer(self.amount, self.destination);
            }
        }


        ////////////////////////////////////////////////////////////////////////
        //
        // The section below can considered as fullfilment of the requirements.
        //
        // As can be seen in the `TransferMoney` struct above, this context
        // needs two Account objects: one to play the MoneySource role, and
        // another to play MoneyDestination. This section provides Accounts
        // objects the requirement to play those two roles.
        //

        // The MoneySource implementation contract for any Account object.
        impl MoneySourceRoleRequirement for data::Account {
            fn available_balance(&self) -> f32 {
                self.current_balance()
            }

            fn decrease_balance(&mut self, amount: f32) -> () {
                self.ledger.push(-amount);
            }
        }

        // The MoneyDestination implementation contract for any Account object.
        impl MoneyDestinationRoleRequirement for data::Account {
            fn increase_balance(&mut self, amount: f32) -> () {
                self.ledger.push(amount);
            }
        }

    }
}

////////////////////////////////////////////////////////////////////////////////
//
// APPLICATION
// -----------
// Application will use both the Data layer and Context and Interaction.
//
mod transfer_money_app {

    use data;
    use context::transfer_money::TransferMoney;

    pub fn run() {
        // Realistically, the Account instances will be fetched from Database or
        // another data source, based on their ID. But in this case, we will
        // just create new instances.
        let an_account      = &mut data::Account { ledger: vec![ 1000_f32 ] };
        let another_account = &mut data::Account { ledger: vec![  100_f32 ] };

        println!("Before: ");
        println!("{:?}", an_account.current_balance());
        println!("{:?}", another_account.current_balance());

        // Execute the money transfer use-case.
        // Note that Rust needs this execution of the context to be enclosed in
        // a scope, because the context requires two *mutable* Accounts, while
        // its outer scope still retaining the ownership later on (for the
        // printing process).

        {
            let mut context = TransferMoney::new(an_account,
                                                 another_account,
                                                 200_f32);
            context.execute();
        }

        println!("After: ");
        println!("{:?}", an_account.current_balance());
        println!("{:?}", another_account.current_balance());
    }
}

fn main() {
    transfer_money_app::run();
}
