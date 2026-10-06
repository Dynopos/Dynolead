<?php

namespace App\Exceptions;

/** The account cannot do this now (trial ended, not enough balance, suspended, per-lead limit reached). */
class AccountLimitReached extends BudgetExceeded {}
