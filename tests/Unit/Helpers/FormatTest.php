<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Format;
use Carbon\Carbon;
use Tests\TestCase;

class FormatTest extends TestCase
{
    public function test_number_formats_with_english_locale(): void
    {
        config(['app.locale' => 'en']);
        app()->setLocale('en');

        $this->assertSame('1,234', Format::number(1234));
        $this->assertSame('1,234.56', Format::number(1234.56, 2));
        $this->assertSame('0', Format::number(0));
    }

    public function test_number_formats_with_danish_locale(): void
    {
        config(['app.locale' => 'da']);
        app()->setLocale('da');

        $this->assertSame('1.234', Format::number(1234));
        $this->assertSame('1.234,56', Format::number(1234.56, 2));
    }

    public function test_currency_formats_with_dollar_sign(): void
    {
        app()->setLocale('en');

        $this->assertSame('$1,234.56', Format::currency(1234.56));
        $this->assertSame('$0.00', Format::currency(0));
        $this->assertSame('$0.1235', Format::currency(0.12345, 4));
    }

    public function test_datetime_returns_dash_for_null(): void
    {
        $this->assertSame('-', Format::dateTime(null));
    }

    public function test_datetime_short_format_english(): void
    {
        app()->setLocale('en');
        $dt = Carbon::create(2026, 3, 12, 14, 30, 45);

        $this->assertSame('2026-03-12 14:30:45', Format::dateTime($dt, 'short'));
    }

    public function test_datetime_short_format_danish(): void
    {
        app()->setLocale('da');
        $dt = Carbon::create(2026, 3, 12, 14, 30, 45);

        $this->assertSame('12/03/2026 14:30:45', Format::dateTime($dt, 'short'));
    }

    public function test_datetime_time_format(): void
    {
        $dt = Carbon::create(2026, 3, 12, 14, 30, 45);
        $this->assertSame('14:30:45', Format::dateTime($dt, 'time'));
    }

    public function test_datetime_date_time_short_format(): void
    {
        app()->setLocale('da');
        $dt = Carbon::create(2026, 3, 12, 14, 30, 0);
        $this->assertSame('12/03 14:30', Format::dateTime($dt, 'date_time_short'));

        app()->setLocale('en');
        $this->assertSame('03/12 14:30', Format::dateTime($dt, 'date_time_short'));
    }

    public function test_datetime_custom_format(): void
    {
        $dt = Carbon::create(2026, 3, 12, 14, 30, 45);
        $this->assertSame('2026', Format::dateTime($dt, 'Y'));
    }

    public function test_relative_returns_dash_for_null(): void
    {
        $this->assertSame('-', Format::relative(null));
    }

    public function test_relative_returns_human_readable(): void
    {
        app()->setLocale('en');
        $dt = Carbon::now()->subMinutes(5);
        $result = Format::relative($dt);

        $this->assertStringContainsString('ago', $result);
    }

    public function test_relative_respects_locale(): void
    {
        app()->setLocale('da');
        Carbon::setLocale('da');
        $dt = Carbon::now()->subMinutes(5);
        $result = Format::relative($dt);

        $this->assertStringContainsString('siden', $result);
    }
}
