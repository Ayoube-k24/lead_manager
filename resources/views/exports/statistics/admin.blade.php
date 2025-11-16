<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Rapport de Statistiques - Super Admin') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        h2 {
            color: #666;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .stat-box {
            display: inline-block;
            margin: 10px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-width: 150px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>
    <h1>{{ __('Rapport de Statistiques Globales') }}</h1>
    <p><strong>{{ __('Généré le') }}:</strong> {{ $generated_at->format('d/m/Y H:i:s') }}</p>

    <h2>{{ __('Statistiques Principales') }}</h2>
    <div>
        <div class="stat-box">
            <div>{{ __('Total Leads') }}</div>
            <div class="stat-value">{{ $stats['total_leads'] }}</div>
        </div>
        <div class="stat-box">
            <div>{{ __('Leads Confirmés') }}</div>
            <div class="stat-value">{{ $stats['confirmed_leads'] }}</div>
        </div>
        <div class="stat-box">
            <div>{{ __('Leads Rejetés') }}</div>
            <div class="stat-value">{{ $stats['rejected_leads'] }}</div>
        </div>
        <div class="stat-box">
            <div>{{ __('Leads en Attente') }}</div>
            <div class="stat-value">{{ $stats['pending_leads'] }}</div>
        </div>
    </div>

    <h2>{{ __('Indicateurs de Performance') }}</h2>
    <table>
        <tr>
            <th>{{ __('Indicateur') }}</th>
            <th>{{ __('Valeur') }}</th>
        </tr>
        <tr>
            <td>{{ __('Taux de Conversion') }}</td>
            <td>{{ $stats['conversion_rate'] }}%</td>
        </tr>
        <tr>
            <td>{{ __('Temps de Traitement Moyen') }}</td>
            <td>{{ $stats['avg_processing_time'] }} {{ __('heures') }}</td>
        </tr>
    </table>

    @if (isset($stats['leads_by_status']))
        <h2>{{ __('Répartition par Statut') }}</h2>
        <table>
            <tr>
                <th>{{ __('Statut') }}</th>
                <th>{{ __('Nombre') }}</th>
            </tr>
            @foreach ($stats['leads_by_status'] as $status => $count)
                <tr>
                    <td>{{ $status }}</td>
                    <td>{{ $count }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        <p>{{ __('Rapport généré automatiquement par') }} {{ config('app.name') }}</p>
    </div>
</body>
</html>

