<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    $input = is_array($json) ? $json : $_POST;
}

$action = $input['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'get_candidates':
            api_get_candidates($pdo, $config);
            break;
        case 'submit_vote':
            api_submit_vote($pdo, $config, $input);
            break;
        case 'get_results':
            api_get_results($pdo, $config);
            break;
        case 'election_status':
            api_election_status($pdo, $config);
            break;
        default:
            json_response(['ok' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

function api_election_status(PDO $pdo, array $config): void
{
    $election = current_election($pdo);
    if (!$election) {
        json_response(['ok' => true, 'status' => 'none', 'title' => '']);
    }
    json_response([
        'ok' => true,
        'status' => $election['status'],
        'title' => $election['title'],
        'id' => (int) $election['id'],
    ]);
}

function api_get_candidates(PDO $pdo, array $config): void
{
    $election = current_election($pdo);
    if (!$election || $election['status'] !== 'live') {
        json_response(['ok' => false, 'error' => 'Voting is not open yet.', 'positions' => []], 403);
    }

    $stmt = $pdo->prepare(
        'SELECT p.id AS position_id, p.name AS position_name, p.sort_order,
                c.id AS candidate_id, c.name, c.class_name, c.photo
         FROM positions p
         INNER JOIN candidates c ON c.position_id = p.id AND c.is_active = 1
         WHERE p.election_id = ? AND p.is_active = 1
         ORDER BY p.sort_order ASC, c.name ASC'
    );
    $stmt->execute([(int) $election['id']]);
    $rows = $stmt->fetchAll();

    $positions = [];
    foreach ($rows as $row) {
        $pid = (int) $row['position_id'];
        if (!isset($positions[$pid])) {
            $positions[$pid] = [
                'id' => $pid,
                'name' => $row['position_name'],
                'candidates' => [],
            ];
        }
        $positions[$pid]['candidates'][] = [
            'id' => (int) $row['candidate_id'],
            'name' => $row['name'],
            'class' => $row['class_name'],
            'photo' => photo_url($config, $row['photo']),
        ];
    }

    json_response([
        'ok' => true,
        'election' => [
            'id' => (int) $election['id'],
            'title' => $election['title'],
            'status' => $election['status'],
        ],
        'positions' => array_values($positions),
    ]);
}

function api_submit_vote(PDO $pdo, array $config, array $input): void
{
    $election = current_election($pdo);
    if (!$election || $election['status'] !== 'live') {
        json_response(['ok' => false, 'error' => 'Voting is closed.'], 403);
    }

    $voterType = strtolower(trim((string) ($input['voter_type'] ?? '')));
    $allowedTypes = ['student', 'staff', 'principal', 'director'];
    if (!in_array($voterType, $allowedTypes, true)) {
        json_response(['ok' => false, 'error' => 'Invalid voter type.'], 422);
    }

    // Special roles need private token + passcode
    if ($voterType === 'principal' || $voterType === 'director') {
        $tokenField = $voterType . '_token';
        $urlToken = (string) ($input['access_token'] ?? '');
        $passcode = (string) ($input['passcode'] ?? '');
        $expectedPass = setting_get(
            $pdo,
            $voterType . '_passcode',
            $config['security'][$voterType . '_passcode'] ?? ''
        );

        if (!hash_equals((string) $election[$tokenField], $urlToken)) {
            json_response(['ok' => false, 'error' => 'Invalid voting link.'], 403);
        }
        if ($expectedPass === '' || !hash_equals($expectedPass, $passcode)) {
            json_response(['ok' => false, 'error' => 'Incorrect passcode.'], 403);
        }
    }

    $votes = $input['votes'] ?? null;
    if (!is_array($votes) || $votes === []) {
        json_response(['ok' => false, 'error' => 'No votes submitted.'], 422);
    }

    // Load active positions
    $posStmt = $pdo->prepare(
        'SELECT id FROM positions WHERE election_id = ? AND is_active = 1'
    );
    $posStmt->execute([(int) $election['id']]);
    $requiredPositions = array_map('intval', array_column($posStmt->fetchAll(), 'id'));
    if ($requiredPositions === []) {
        json_response(['ok' => false, 'error' => 'No positions configured.'], 422);
    }

    $normalized = [];
    foreach ($votes as $vote) {
        $positionId = (int) ($vote['position_id'] ?? 0);
        $candidateId = (int) ($vote['candidate_id'] ?? 0);
        if ($positionId <= 0 || $candidateId <= 0) {
            continue;
        }
        $normalized[$positionId] = $candidateId;
    }

    foreach ($requiredPositions as $pid) {
        if (!isset($normalized[$pid])) {
            json_response(['ok' => false, 'error' => 'Please select one candidate for every position.'], 422);
        }
    }

    // Validate candidates belong to positions
    $check = $pdo->prepare(
        'SELECT id FROM candidates
         WHERE id = ? AND position_id = ? AND election_id = ? AND is_active = 1'
    );
    foreach ($normalized as $pid => $cid) {
        $check->execute([$cid, $pid, (int) $election['id']]);
        if (!$check->fetch()) {
            json_response(['ok' => false, 'error' => 'Invalid candidate selection.'], 422);
        }
    }

    $voterToken = ensure_voter_cookie();
    // For principal/director, force unique token per role so they can only vote once
    if ($voterType === 'principal' || $voterType === 'director') {
        $voterToken = $voterType . '_once';
    }

    // Already voted?
    $exists = $pdo->prepare(
        'SELECT id FROM ballots WHERE election_id = ? AND voter_type = ? AND voter_token = ? LIMIT 1'
    );
    $exists->execute([(int) $election['id'], $voterType, $voterToken]);
    if ($exists->fetch()) {
        json_response(['ok' => false, 'error' => 'You have already voted in this election.'], 409);
    }

    $pdo->beginTransaction();
    try {
        $ballotStmt = $pdo->prepare(
            'INSERT INTO ballots (election_id, voter_type, voter_token, ip_hash, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ballotStmt->execute([
            (int) $election['id'],
            $voterType,
            $voterToken,
            ip_hash(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        $ballotId = (int) $pdo->lastInsertId();

        $voteStmt = $pdo->prepare(
            'INSERT INTO votes (ballot_id, election_id, position_id, candidate_id, voter_type)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($normalized as $pid => $cid) {
            $voteStmt->execute([
                $ballotId,
                (int) $election['id'],
                $pid,
                $cid,
                $voterType,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if (str_contains($e->getMessage(), 'uq_ballot_token') || str_contains($e->getMessage(), 'Duplicate')) {
            json_response(['ok' => false, 'error' => 'You have already voted in this election.'], 409);
        }
        throw $e;
    }

    json_response(['ok' => true, 'message' => 'Your vote has been submitted successfully!']);
}

function api_get_results(PDO $pdo, array $config): void
{
    $election = current_election($pdo);
    if (!$election) {
        json_response(['ok' => true, 'winners' => [], 'results' => [], 'special_votes' => [], 'status' => 'none']);
    }

    // Which special roles have voted
    $specialStmt = $pdo->prepare(
        "SELECT DISTINCT voter_type FROM ballots
         WHERE election_id = ? AND voter_type IN ('principal','director')"
    );
    $specialStmt->execute([(int) $election['id']]);
    $specialVotes = array_column($specialStmt->fetchAll(), 'voter_type');

    $hideCounts = in_array('principal', $specialVotes, true)
        || in_array('director', $specialVotes, true);

    $stmt = $pdo->prepare(
        'SELECT p.id AS position_id, p.name AS position_name, p.sort_order,
                c.id AS candidate_id, c.name, c.class_name, c.photo,
                COALESCE(COUNT(v.id), 0) AS vote_count,
                SUM(CASE WHEN v.voter_type = "student" THEN 1 ELSE 0 END) AS student_votes,
                SUM(CASE WHEN v.voter_type = "staff" THEN 1 ELSE 0 END) AS staff_votes,
                SUM(CASE WHEN v.voter_type = "principal" THEN 1 ELSE 0 END) AS principal_votes,
                SUM(CASE WHEN v.voter_type = "director" THEN 1 ELSE 0 END) AS director_votes
         FROM positions p
         INNER JOIN candidates c ON c.position_id = p.id AND c.is_active = 1
         LEFT JOIN votes v ON v.candidate_id = c.id AND v.position_id = p.id AND v.election_id = p.election_id
         WHERE p.election_id = ? AND p.is_active = 1
         GROUP BY p.id, c.id
         ORDER BY p.sort_order ASC, vote_count DESC, c.name ASC'
    );
    $stmt->execute([(int) $election['id']]);
    $rows = $stmt->fetchAll();

    $results = [];
    $winners = [];

    foreach ($rows as $row) {
        $pname = $row['position_name'];
        if (!isset($results[$pname])) {
            $results[$pname] = [];
        }
        $entry = [
            'id' => (int) $row['candidate_id'],
            'name' => $row['name'],
            'class' => $row['class_name'],
            'photo' => photo_url($config, $row['photo']),
            'votes' => $hideCounts ? null : (int) $row['vote_count'],
            'breakdown' => $hideCounts ? null : [
                'student' => (int) $row['student_votes'],
                'staff' => (int) $row['staff_votes'],
                'principal' => (int) $row['principal_votes'],
                'director' => (int) $row['director_votes'],
            ],
        ];
        $results[$pname][] = $entry;

        if (!isset($winners[$pname])) {
            $winners[$pname] = [
                'name' => $row['name'],
                'class' => $row['class_name'],
                'photo' => photo_url($config, $row['photo']),
                'votes' => $hideCounts ? null : (int) $row['vote_count'],
            ];
        }
    }

    $totalsStmt = $pdo->prepare(
        'SELECT voter_type, COUNT(*) AS total FROM ballots WHERE election_id = ? GROUP BY voter_type'
    );
    $totalsStmt->execute([(int) $election['id']]);
    $totals = [];
    foreach ($totalsStmt->fetchAll() as $t) {
        $totals[$t['voter_type']] = (int) $t['total'];
    }

    json_response([
        'ok' => true,
        'status' => $election['status'],
        'title' => $election['title'],
        'hide_counts' => $hideCounts,
        'special_votes' => $specialVotes,
        'ballot_totals' => $totals,
        'winners' => $winners,
        'results' => $results,
    ]);
}
