<?php

namespace Sapiensly\OpenaiAgents\Example\AI;

/**
 * Handles weather-related operations and calculations.
 * This file is part of the Sapiensly OpenAI Agents package, it provides a simple weather service example for demonstration purposes.
 * OpenAI expects snake_case for function names, but this package allows using both snake_case and camelCase.
 */
class WeatherService
{
    /**
     * Get current weather for a location
     * @param string $location City and country e.g. Madrid, Spain
     */
    public function getWeather(string $location): array
    {
        return [
            'location' => $location,
            'temperature' => 22,
            'condition' => 'sunny',
            'humidity' => 65
        ];
    }

    /**
     * Calculate wind chill factor
     * @param float $temperature Temperature in Celsius (required)
     * @param float $windSpeed Wind speed in km/h (required)
     * @return float Wind chill factor in Celsius
    */
    public function calculateWindChill(float $temperature, float $windSpeed): float
    {
        return 13.12 + 0.6215 * $temperature - 11.37 * pow($windSpeed, 0.16) + 0.3965 * $temperature * pow($windSpeed, 0.16);
    }
}
