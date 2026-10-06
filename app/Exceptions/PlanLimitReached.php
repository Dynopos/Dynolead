<?php

namespace App\Exceptions;

/** The customer's plan does not allow this (expired, quota used up, too many products). */
class PlanLimitReached extends BudgetExceeded {}
