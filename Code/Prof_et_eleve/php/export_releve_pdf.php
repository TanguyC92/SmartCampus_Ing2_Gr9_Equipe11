<?php
// export_releve_pdf.php — Génère un relevé de notes PDF pour un étudiant
require_once '../../Connexion/config.php';

// Désactiver les headers JSON par défaut (on va envoyer du HTML)
// Note: config.php envoie des headers, on les ignore ici avec output buffering
ob_start();
$dummy = ob_get_clean(); // vider les headers de config.php

$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;
$id_semestre = isset($_GET['id_semestre']) ? (int)$_GET['id_semestre'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'actuel'; // 'actuel' ou 'archive'

if ($id_user === 0) {
    header('Content-Type: text/plain');
    echo "Erreur : ID utilisateur manquant.";
    exit;
}

// Infos étudiant
$sqlUser = "SELECT U.nom, U.prenom, U.email, ET.id_etudiant,
                   D.nom_departement, ET.niveau
            FROM user U
            JOIN etudiant ET ON ET.id_user = U.id_user
            LEFT JOIN departement D ON D.id_departement = ET.id_departement
            WHERE U.id_user = $id_user LIMIT 1";
$resUser = mysqli_query($conn, $sqlUser);
if (!$resUser || mysqli_num_rows($resUser) === 0) {
    header('Content-Type: text/plain');
    echo "Étudiant introuvable.";
    exit;
}
$etudiant = mysqli_fetch_assoc($resUser);
$id_etudiant = (int)$etudiant['id_etudiant'];

$semNom = '';
$semAnnee = '';
$semStatut = 'actif';
$cours = [];
$moyenneGenerale = null;

if ($type === 'archive' && $id_semestre > 0) {
    // Données archivées
    $sqlSem = "SELECT * FROM semestre WHERE id_semestre=$id_semestre LIMIT 1";
    $resSem = mysqli_query($conn, $sqlSem);
    $sem = $resSem ? mysqli_fetch_assoc($resSem) : null;
    if (!$sem) { echo "Semestre introuvable."; exit; }
    $semNom = $sem['nom'];
    $semAnnee = $sem['annee_academique'];
    $semStatut = $sem['statut'];

    $sqlCours = "SELECT DISTINCT id_cours, code_cours, titre_cours, coefficient_cours, moyenne_cours, moyenne_generale
                 FROM note_archive WHERE id_semestre=$id_semestre AND id_etudiant=$id_etudiant
                 ORDER BY titre_cours ASC";
    $resCours = mysqli_query($conn, $sqlCours);
    while ($c = mysqli_fetch_assoc($resCours)) {
        $idCours = (int)$c['id_cours'];
        $sqlNotes = "SELECT type_evaluation, note FROM note_archive
                     WHERE id_semestre=$id_semestre AND id_etudiant=$id_etudiant AND id_cours=$idCours
                     ORDER BY type_evaluation ASC";
        $resNotes = mysqli_query($conn, $sqlNotes);
        $notes = [];
        while ($n = mysqli_fetch_assoc($resNotes)) $notes[] = $n;
        $cours[] = array_merge($c, ['notes' => $notes]);
        if ($moyenneGenerale === null && $c['moyenne_generale'] !== null) {
            $moyenneGenerale = $c['moyenne_generale'];
        }
    }
} else {
    // Notes actuelles (semestre actif)
    $sqlSem = "SELECT * FROM semestre WHERE statut='actif' ORDER BY date_debut DESC LIMIT 1";
    $resSem = mysqli_query($conn, $sqlSem);
    $sem = $resSem ? mysqli_fetch_assoc($resSem) : null;
    $semNom = $sem ? $sem['nom'] : 'Semestre en cours';
    $semAnnee = $sem ? $sem['annee_academique'] : date('Y') . '-' . (date('Y')+1);

    $sqlCours = "SELECT C.id_cours, C.code_cours, C.titre, C.coefficient AS coefficient_cours, I.id_inscription
                 FROM inscription I
                 JOIN etudiant ET ON I.id_etudiant = ET.id_etudiant
                 JOIN cours C ON I.id_cours = C.id_cours
                 WHERE ET.id_user = $id_user ORDER BY C.titre ASC";
    $resCours = mysqli_query($conn, $sqlCours);
    $totalCoeffGen = 0; $sommeGen = 0;
    while ($c = mysqli_fetch_assoc($resCours)) {
        $idIns = (int)$c['id_inscription'];
        $idCours = (int)$c['id_cours'];
        $sqlNotes = "SELECT type_evaluation, note FROM note WHERE id_inscription=$idIns ORDER BY type_evaluation ASC";
        $resNotes = mysqli_query($conn, $sqlNotes);
        $notes = [];
        while ($n = mysqli_fetch_assoc($resNotes)) $notes[] = $n;

        // Calcul moyenne cours
        $sqlConfig = "SELECT * FROM evaluation_config WHERE id_cours=$idCours";
        $resConfig = mysqli_query($conn, $sqlConfig);
        $configs = [];
        while ($cfg = mysqli_fetch_assoc($resConfig)) {
            $cfg['pourcentages'] = json_decode($cfg['pourcentages'] ?? '[]', true);
            $configs[] = $cfg;
        }
        $moyC = null; $tcCat = 0; $spCat = 0;
        foreach ($configs as $cfg) {
            $catKey = $cfg['categorie'];
            $coeffCat = (float)$cfg['coefficient_categorie'];
            $nbNotes = (int)$cfg['nombre_notes'];
            $pcts = $cfg['pourcentages'];
            $sommeCat = 0; $totalPct = 0;
            for ($i = 1; $i <= $nbNotes; $i++) {
                $typeEval = $catKey . '_' . $i;
                $pct = isset($pcts[$i-1]) ? (float)$pcts[$i-1] : 0;
                foreach ($notes as $n) {
                    if ($n['type_evaluation'] === $typeEval) {
                        $sommeCat += (float)$n['note'] * ($pct / 100);
                        $totalPct += $pct;
                        break;
                    }
                }
            }
            if ($totalPct > 0) {
                $moyCat = ($sommeCat / $totalPct) * 100;
                $spCat += $moyCat * $coeffCat;
                $tcCat += $coeffCat;
            }
        }
        if ($tcCat > 0) {
            $moyC = round($spCat / $tcCat, 2);
            $sommeGen += $moyC * (float)$c['coefficient_cours'];
            $totalCoeffGen += (float)$c['coefficient_cours'];
        }
        $cours[] = [
            'id_cours' => $idCours,
            'code_cours' => $c['code_cours'],
            'titre_cours' => $c['titre'],
            'coefficient_cours' => $c['coefficient_cours'],
            'moyenne_cours' => $moyC,
            'notes' => $notes
        ];
    }
    if ($totalCoeffGen > 0) $moyenneGenerale = round($sommeGen / $totalCoeffGen, 2);
}

// Labels des types d'évaluation
$LABELS = ['controle_continu' => 'Contrôle Continu', 'examen' => 'Examen', 'projet' => 'Projet'];
function getLabel($typeEval, $labels) {
    $parts = explode('_', $typeEval);
    $num = array_pop($parts);
    $key = implode('_', $parts);
    $catLabel = $labels[$key] ?? ucfirst($key);
    return "$catLabel n°$num";
}

function fmtNote($v) {
    if ($v === null || $v === '') return '--';
    return number_format((float)$v, 2, ',', '');
}

function mentionFromMoy($m) {
    if ($m === null) return '';
    if ($m >= 16) return 'Très Bien';
    if ($m >= 14) return 'Bien';
    if ($m >= 12) return 'Assez Bien';
    if ($m >= 10) return 'Passable';
    return 'Insuffisant';
}

function colorFromMoy($m) {
    if ($m === null) return '#64748b';
    if ($m >= 14) return '#059669';
    if ($m >= 10) return '#2563eb';
    return '#dc2626';
}

$dateGen = date('d/m/Y');
$nomEtu = htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']);
$emailEtu = htmlspecialchars($etudiant['email'] ?? '');
$niveauEtu = htmlspecialchars($etudiant['niveau'] ?? '');
$deptEtu = htmlspecialchars($etudiant['nom_departement'] ?? '');
$moyGen = $moyenneGenerale;
$mentionGen = mentionFromMoy($moyGen);
$colorMoyGen = colorFromMoy($moyGen);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Relevé de notes — <?= $nomEtu ?></title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap');
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', Arial, sans-serif; background: #f8fafc; color: #0f172a; font-size: 13px; }

    .page { width: 210mm; min-height: 297mm; margin: 0 auto; background: white; padding: 0; }

    /* Header */
    .doc-header { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; padding: 28px 36px 24px; }
    .header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .school-info .school-name { font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
    .school-info .school-sub { font-size: 12px; opacity: 0.6; margin-top: 2px; }
    .doc-badge { background: rgba(59,130,246,0.25); border: 1px solid rgba(59,130,246,0.4); border-radius: 20px; padding: 6px 16px; font-size: 12px; font-weight: 600; color: #93c5fd; }
    .header-main { display: flex; justify-content: space-between; align-items: flex-end; }
    .doc-title { font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
    .doc-title span { color: #60a5fa; }
    .doc-meta { text-align: right; font-size: 11px; opacity: 0.65; line-height: 1.7; }

    /* Student card */
    .student-section { padding: 20px 36px; border-bottom: 2px solid #f1f5f9; background: #fff; }
    .student-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
    .student-field { }
    .student-field .label { font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; margin-bottom: 3px; }
    .student-field .value { font-size: 14px; font-weight: 600; color: #0f172a; }
    .student-field .value.accent { color: #2563eb; }

    /* General average banner */
    .avg-banner { margin: 20px 36px; border-radius: 12px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; }
    .avg-banner.good { background: linear-gradient(135deg, #d1fae5, #a7f3d0); border: 1px solid #6ee7b7; }
    .avg-banner.ok { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border: 1px solid #93c5fd; }
    .avg-banner.bad { background: linear-gradient(135deg, #fee2e2, #fecaca); border: 1px solid #fca5a5; }
    .avg-banner .avg-label { font-size: 13px; font-weight: 600; color: #374151; }
    .avg-banner .avg-sub { font-size: 11px; color: #6b7280; margin-top: 2px; }
    .avg-banner .avg-val { font-size: 36px; font-weight: 700; }
    .avg-banner .avg-mention { font-size: 13px; font-weight: 600; margin-top: 2px; text-align: right; }

    /* Courses section */
    .courses-section { padding: 0 36px 28px; }
    .section-title { font-size: 11px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #64748b; margin: 20px 0 12px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }

    /* Course card */
    .course-card { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
    .course-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .course-title-block { }
    .course-code { font-size: 10px; font-weight: 700; letter-spacing: 1px; color: #94a3b8; text-transform: uppercase; }
    .course-title { font-size: 14px; font-weight: 600; color: #0f172a; margin-top: 1px; }
    .course-right { display: flex; align-items: center; gap: 12px; }
    .course-coeff { font-size: 11px; color: #64748b; background: #e2e8f0; border-radius: 20px; padding: 3px 10px; }
    .course-avg { font-size: 18px; font-weight: 700; }
    .course-body { padding: 12px 16px; }
    .notes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
    .note-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; }
    .note-item .note-type { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
    .note-item .note-val { font-size: 16px; font-weight: 700; margin-top: 2px; }
    .note-item.has-note { border-color: #bfdbfe; background: #eff6ff; }
    .note-item.has-note .note-val { color: #2563eb; }
    .note-item.no-note .note-val { color: #d1d5db; }
    .no-notes-msg { font-size: 12px; color: #94a3b8; font-style: italic; }

    /* Footer */
    .doc-footer { border-top: 2px solid #f1f5f9; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #94a3b8; background: #f8fafc; }
    .footer-stamp { display: flex; align-items: center; gap: 8px; }
    .stamp-box { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 4px 12px; font-size: 10px; color: #94a3b8; }

    /* Status badge */
    .status-badge { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; padding: 3px 10px; border-radius: 20px; }
    .status-badge.actif { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
    .status-badge.cloture { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

    @media print {
        body { background: white; }
        .page { margin: 0; box-shadow: none; }
        .print-btn { display: none !important; }
    }
</style>
</head>
<body>

<div style="text-align:center; padding:12px; background:#1e293b; color:white; font-size:12px; display:flex; gap:12px; justify-content:center;" class="print-btn">
    <button onclick="window.print()" style="background:#3b82f6; color:white; border:none; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:600; cursor:pointer;">🖨️ Imprimer / Télécharger en PDF</button>
    <button onclick="window.close()" style="background:#475569; color:white; border:none; border-radius:8px; padding:8px 20px; font-size:13px; font-weight:600; cursor:pointer;">✕ Fermer</button>
</div>

<div class="page">
    <!-- Header -->
    <div class="doc-header">
        <div class="header-top">
            <div class="school-info">
                <div class="school-name">🎓 SmartCampus</div>
                <div class="school-sub">Système de Gestion Académique</div>
            </div>
            <div class="doc-badge">RELEVÉ DE NOTES OFFICIEL</div>
        </div>
        <div class="header-main">
            <div>
                <div class="doc-title">Relevé de <span>Notes</span></div>
                <div style="font-size:13px; opacity:0.7; margin-top:4px;"><?= htmlspecialchars($semNom) ?> — <?= htmlspecialchars($semAnnee) ?></div>
            </div>
            <div class="doc-meta">
                Généré le <?= $dateGen ?><br>
                Statut : <span class="status-badge <?= $semStatut ?>"><?= $semStatut === 'cloture' ? '✓ Validé' : '⏳ En cours' ?></span>
            </div>
        </div>
    </div>

    <!-- Student Info -->
    <div class="student-section">
        <div class="student-grid">
            <div class="student-field">
                <div class="label">Étudiant(e)</div>
                <div class="value"><?= $nomEtu ?></div>
            </div>
            <div class="student-field">
                <div class="label">Email</div>
                <div class="value accent"><?= $emailEtu ?></div>
            </div>
            <div class="student-field">
                <div class="label">Niveau / Promotion</div>
                <div class="value"><?= $niveauEtu ?: 'N/A' ?></div>
            </div>
            <?php if ($deptEtu): ?>
            <div class="student-field">
                <div class="label">Département</div>
                <div class="value"><?= $deptEtu ?></div>
            </div>
            <?php endif; ?>
            <div class="student-field">
                <div class="label">Semestre</div>
                <div class="value"><?= htmlspecialchars($semNom) ?></div>
            </div>
            <div class="student-field">
                <div class="label">Année académique</div>
                <div class="value"><?= htmlspecialchars($semAnnee) ?></div>
            </div>
        </div>
    </div>

    <!-- Average Banner -->
    <?php if ($moyGen !== null): ?>
    <?php
        $bannerClass = $moyGen >= 14 ? 'good' : ($moyGen >= 10 ? 'ok' : 'bad');
    ?>
    <div class="avg-banner <?= $bannerClass ?>">
        <div>
            <div class="avg-label">📊 Moyenne Générale du Semestre</div>
            <div class="avg-sub">Calculée sur l'ensemble des matières (pondérée par les coefficients)</div>
        </div>
        <div style="text-align:right;">
            <div class="avg-val" style="color:<?= colorFromMoy($moyGen) ?>"><?= fmtNote($moyGen) ?> / 20</div>
            <div class="avg-mention" style="color:<?= colorFromMoy($moyGen) ?>"><?= $mentionGen ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Courses -->
    <div class="courses-section">
        <div class="section-title">Détail des matières (<?= count($cours) ?> cours)</div>

        <?php if (empty($cours)): ?>
            <p style="color:#94a3b8; font-style:italic; text-align:center; padding:30px;">Aucune note disponible pour ce semestre.</p>
        <?php else: foreach ($cours as $c):
            $moy = $c['moyenne_cours'];
            $colorMoy = colorFromMoy($moy);
            $mention = mentionFromMoy($moy);
        ?>
        <div class="course-card">
            <div class="course-header">
                <div class="course-title-block">
                    <div class="course-code"><?= htmlspecialchars($c['code_cours']) ?></div>
                    <div class="course-title"><?= htmlspecialchars($c['titre_cours']) ?></div>
                </div>
                <div class="course-right">
                    <div class="course-coeff">Coeff. <?= htmlspecialchars($c['coefficient_cours']) ?></div>
                    <div>
                        <div class="course-avg" style="color:<?= $colorMoy ?>"><?= $moy !== null ? fmtNote($moy) . ' / 20' : '-- / 20' ?></div>
                        <?php if ($mention && $moy !== null): ?>
                        <div style="font-size:10px; color:<?= $colorMoy ?>; text-align:right; font-weight:600;"><?= $mention ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="course-body">
                <?php if (empty($c['notes'])): ?>
                    <span class="no-notes-msg">Aucune note saisie</span>
                <?php else: ?>
                <div class="notes-grid">
                    <?php foreach ($c['notes'] as $n):
                        $hasNote = $n['note'] !== null && $n['note'] !== '';
                    ?>
                    <div class="note-item <?= $hasNote ? 'has-note' : 'no-note' ?>">
                        <div class="note-type"><?= htmlspecialchars(getLabel($n['type_evaluation'], $LABELS)) ?></div>
                        <div class="note-val"><?= $hasNote ? fmtNote($n['note']) . ' / 20' : '— / 20' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <div class="footer-stamp">
            <div class="stamp-box">Document généré le <?= $dateGen ?> via SmartCampus</div>
            <?php if ($semStatut === 'cloture'): ?>
            <div class="stamp-box">✓ Notes officiellement validées</div>
            <?php else: ?>
            <div class="stamp-box">⚠️ Semestre en cours — Notes provisoires</div>
            <?php endif; ?>
        </div>
        <div>smartcampus.edu • support@smartcampus.edu</div>
    </div>
</div>

</body>
</html>
