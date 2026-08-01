<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * How a report treats a subject converting on the same goal more than once.
 *
 * Fixed here rather than in the analytics package because changing it changes
 * what the numbers mean: counting every purchase measures a different quantity
 * than counting converted subjects, and a report must state which it is.
 *
 * @api
 */
enum RepeatedConversionPolicy: string
{
    /**
     * Count one conversion per (subject, goal, experiment, revision).
     *
     * The default: conversion rate is a share of subjects, so a single subject
     * converting ten times must not outweigh ten subjects converting once.
     */
    case FirstOnly = 'first_only';

    /**
     * Count every conversion. Meant for value metrics, where repetition is the
     * quantity being measured rather than noise.
     */
    case All = 'all';
}
