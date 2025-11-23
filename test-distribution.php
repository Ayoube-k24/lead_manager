<?php

/**
 * Script de test manuel pour vérifier la distribution automatique des leads
 * 
 * Usage: php test-distribution.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;

echo "=== Test de Distribution Automatique des Leads ===\n\n";

// Test 1: Mode round_robin
echo "Test 1: Distribution automatique en mode round_robin\n";
echo str_repeat('-', 50)."\n";

$callCenter1 = CallCenter::factory()->create(['distribution_method' => 'round_robin', 'name' => 'Centre Test Round Robin']);
$agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent', 'slug' => 'agent']);

$agent1 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter1->id, 'name' => 'Agent 1']);
$agent2 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter1->id, 'name' => 'Agent 2']);

$smtpProfile = SmtpProfile::factory()->create();
$emailTemplate = EmailTemplate::factory()->create();
$form1 = Form::factory()->create([
    'call_center_id' => $callCenter1->id,
    'smtp_profile_id' => $smtpProfile->id,
    'email_template_id' => $emailTemplate->id,
    'name' => 'Formulaire Test Round Robin',
]);

$lead1 = Lead::factory()->create([
    'form_id' => $form1->id,
    'call_center_id' => $callCenter1->id,
    'status' => 'pending_email',
    'assigned_to' => null,
    'email' => 'test1@example.com',
]);

echo "Lead créé: ID={$lead1->id}, Email={$lead1->email}, Status={$lead1->status}, Assigned={$lead1->assigned_to}\n";

// Confirmer l'email
$lead1->confirmEmail();
$lead1->refresh();

echo "Après confirmation: Status={$lead1->status}, Assigned={$lead1->assigned_to}\n";

if ($lead1->assigned_to) {
    $assignedAgent = User::find($lead1->assigned_to);
    echo "✅ SUCCESS: Lead assigné automatiquement à {$assignedAgent->name} (ID: {$assignedAgent->id})\n";
    echo "   Status final: {$lead1->status}\n";
} else {
    echo "❌ FAILED: Lead non assigné\n";
}

echo "\n";

// Test 2: Mode manual
echo "Test 2: Pas de distribution automatique en mode manual\n";
echo str_repeat('-', 50)."\n";

$callCenter2 = CallCenter::factory()->create(['distribution_method' => 'manual', 'name' => 'Centre Test Manual']);
$agent3 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter2->id, 'name' => 'Agent 3']);

$form2 = Form::factory()->create([
    'call_center_id' => $callCenter2->id,
    'smtp_profile_id' => $smtpProfile->id,
    'email_template_id' => $emailTemplate->id,
    'name' => 'Formulaire Test Manual',
]);

$lead2 = Lead::factory()->create([
    'form_id' => $form2->id,
    'call_center_id' => $callCenter2->id,
    'status' => 'pending_email',
    'assigned_to' => null,
    'email' => 'test2@example.com',
]);

echo "Lead créé: ID={$lead2->id}, Email={$lead2->email}, Status={$lead2->status}, Assigned={$lead2->assigned_to}\n";

// Confirmer l'email
$lead2->confirmEmail();
$lead2->refresh();

echo "Après confirmation: Status={$lead2->status}, Assigned={$lead2->assigned_to}\n";

if ($lead2->assigned_to === null) {
    echo "✅ SUCCESS: Lead non assigné (mode manual)\n";
    echo "   Status final: {$lead2->status}\n";
} else {
    echo "❌ FAILED: Lead assigné alors qu'il devrait être en mode manual\n";
}

echo "\n";

// Test 3: Mode weighted
echo "Test 3: Distribution automatique en mode weighted\n";
echo str_repeat('-', 50)."\n";

$callCenter3 = CallCenter::factory()->create(['distribution_method' => 'weighted', 'name' => 'Centre Test Weighted']);
$agent4 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter3->id, 'name' => 'Agent 4']);

$form3 = Form::factory()->create([
    'call_center_id' => $callCenter3->id,
    'smtp_profile_id' => $smtpProfile->id,
    'email_template_id' => $emailTemplate->id,
    'name' => 'Formulaire Test Weighted',
]);

$lead3 = Lead::factory()->create([
    'form_id' => $form3->id,
    'call_center_id' => $callCenter3->id,
    'status' => 'pending_email',
    'assigned_to' => null,
    'email' => 'test3@example.com',
]);

echo "Lead créé: ID={$lead3->id}, Email={$lead3->email}, Status={$lead3->status}, Assigned={$lead3->assigned_to}\n";

// Confirmer l'email
$lead3->confirmEmail();
$lead3->refresh();

echo "Après confirmation: Status={$lead3->status}, Assigned={$lead3->assigned_to}\n";

if ($lead3->assigned_to) {
    $assignedAgent = User::find($lead3->assigned_to);
    echo "✅ SUCCESS: Lead assigné automatiquement à {$assignedAgent->name} (ID: {$assignedAgent->id})\n";
    echo "   Status final: {$lead3->status}\n";
} else {
    echo "❌ FAILED: Lead non assigné\n";
}

echo "\n";

// Test 4: call_center_id depuis le formulaire
echo "Test 4: call_center_id récupéré depuis le formulaire\n";
echo str_repeat('-', 50)."\n";

$callCenter4 = CallCenter::factory()->create(['distribution_method' => 'round_robin', 'name' => 'Centre Test Form']);
$agent5 = User::factory()->create(['role_id' => $agentRole->id, 'call_center_id' => $callCenter4->id, 'name' => 'Agent 5']);

$form4 = Form::factory()->create([
    'call_center_id' => $callCenter4->id,
    'smtp_profile_id' => $smtpProfile->id,
    'email_template_id' => $emailTemplate->id,
    'name' => 'Formulaire Test Form',
]);

$lead4 = Lead::factory()->create([
    'form_id' => $form4->id,
    'call_center_id' => null, // Pas de call_center_id initialement
    'status' => 'pending_email',
    'assigned_to' => null,
    'email' => 'test4@example.com',
]);

echo "Lead créé: ID={$lead4->id}, Email={$lead4->email}, CallCenter={$lead4->call_center_id}, Assigned={$lead4->assigned_to}\n";

// Confirmer l'email
$lead4->confirmEmail();
$lead4->refresh();

echo "Après confirmation: CallCenter={$lead4->call_center_id}, Assigned={$lead4->assigned_to}\n";

if ($lead4->call_center_id === $callCenter4->id && $lead4->assigned_to) {
    $assignedAgent = User::find($lead4->assigned_to);
    echo "✅ SUCCESS: call_center_id récupéré depuis le formulaire et lead assigné à {$assignedAgent->name}\n";
} else {
    echo "❌ FAILED: call_center_id ou assignation incorrecte\n";
}

echo "\n";
echo "=== Tests terminés ===\n";

