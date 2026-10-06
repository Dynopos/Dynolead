<?php

namespace App\Exceptions;

/** The account cannot do this now (not enough credits, suspended, per-lead limit reached). */
class AccountLimitReached extends BudgetExceeded {}
