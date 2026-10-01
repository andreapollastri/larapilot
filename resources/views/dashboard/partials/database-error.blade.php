<section class="card db-error" role="alert">
    <h3>The database cannot be read</h3>
    <p>Larapilot reads the connection named by <code>DB_CONNECTION</code> with the <code>DB_*</code> values in <code>.env</code> — the one the application uses. Check that the server is running and those values are right; <code>LARAPILOT_DATABASE_VIEWER_CONNECTION</code> points the page at another connection of <code>config/database.php</code>.</p>
    <pre>{{ $error }}</pre>
</section>
