;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
;; Data
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

(defrecord Account [entries])

(defn current-balance [account]
  (reduce + (-> account :entries)))

(defn add-entry [account amount]
  (assoc account :entries (conj (-> account :entries) amount)))


;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
;; Context
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

(defprotocol Source
  (available-balance [this])
  (credit [this amount]))

(defprotocol Destination
  (debit [this amount]))

(defn transfer [source destination amount]
  (dosync
   (when (< (available-balance @source) amount)
     (throw (ex-info "Insufficient fund."
                     {:account @source
                      :balance (available-balance @source)})))
   (alter source      credit amount)
   (alter destination debit  amount)))


;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
;; Application
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

(extend-type Account
  Source
  (available-balance [this] (current-balance this))
  (credit [this amount] (add-entry this (- amount))))

(extend-type Account
  Destination
  (debit [this amount] (add-entry this amount)))

;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

(def account1 (ref (Account. [1000])))
(def account2 (ref (Account. [ 100])))

(println "Before: ")
(println "account1: " (current-balance @account1))
(println "account2: " (current-balance @account2))

(transfer account1 account2 200)

(println "After: ")
(println "account1: " (current-balance @account1))
(println "account2: " (current-balance @account2))

