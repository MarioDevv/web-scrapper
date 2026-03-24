CREATE TABLE IF NOT EXISTS folders (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    color TEXT NOT NULL DEFAULT '#5b8af5',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audits (
    id TEXT PRIMARY KEY,
    folder_id TEXT,
    seed_url TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    max_pages INTEGER NOT NULL DEFAULT 500,
    max_depth INTEGER NOT NULL DEFAULT 10,
    concurrency INTEGER NOT NULL DEFAULT 5,
    request_delay REAL NOT NULL DEFAULT 0.25,
    respect_robots_txt INTEGER NOT NULL DEFAULT 1,
    custom_user_agent TEXT,
    exclude_patterns TEXT NOT NULL DEFAULT '[]',
    include_patterns TEXT NOT NULL DEFAULT '[]',
    follow_external_links INTEGER NOT NULL DEFAULT 0,
    crawl_subdomains INTEGER NOT NULL DEFAULT 0,
    pages_discovered INTEGER NOT NULL DEFAULT 0,
    pages_crawled INTEGER NOT NULL DEFAULT 0,
    pages_failed INTEGER NOT NULL DEFAULT 0,
    issues_found INTEGER NOT NULL DEFAULT 0,
    errors_found INTEGER NOT NULL DEFAULT 0,
    warnings_found INTEGER NOT NULL DEFAULT 0,
    started_at TEXT,
    completed_at TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pages (
    id TEXT PRIMARY KEY,
    audit_id TEXT NOT NULL,
    url TEXT NOT NULL,
    status_code INTEGER NOT NULL,
    content_type TEXT,
    body_size INTEGER NOT NULL DEFAULT 0,
    response_time REAL NOT NULL DEFAULT 0,
    final_url TEXT,
    headers TEXT NOT NULL DEFAULT '{}',
    crawl_depth INTEGER NOT NULL DEFAULT 0,
    is_html INTEGER NOT NULL DEFAULT 0,
    title TEXT,
    meta_description TEXT,
    h1s TEXT NOT NULL DEFAULT '[]',
    h2s TEXT NOT NULL DEFAULT '[]',
    heading_hierarchy TEXT NOT NULL DEFAULT '[]',
    charset TEXT,
    viewport TEXT,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    word_count INTEGER NOT NULL DEFAULT 0,
    lang TEXT,
    noindex INTEGER NOT NULL DEFAULT 0,
    nofollow INTEGER NOT NULL DEFAULT 0,
    noarchive INTEGER NOT NULL DEFAULT 0,
    nosnippet INTEGER NOT NULL DEFAULT 0,
    noimageindex INTEGER NOT NULL DEFAULT 0,
    max_snippet INTEGER,
    max_image_preview TEXT,
    max_video_preview INTEGER,
    canonical TEXT,
    directive_source TEXT,
    exact_hash TEXT,
    sim_hash INTEGER,
    redirect_chain TEXT NOT NULL DEFAULT '[]',
    links TEXT NOT NULL DEFAULT '[]',
    hreflangs TEXT NOT NULL DEFAULT '[]',
    crawled_at TEXT NOT NULL,
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pages_audit_id ON pages(audit_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_pages_audit_url ON pages(audit_id, url);

CREATE TABLE IF NOT EXISTS issues (
    id TEXT PRIMARY KEY,
    page_id TEXT NOT NULL,
    category TEXT NOT NULL,
    severity TEXT NOT NULL,
    code TEXT NOT NULL,
    message TEXT NOT NULL,
    context TEXT,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_issues_page_id ON issues(page_id);
CREATE INDEX IF NOT EXISTS idx_issues_category ON issues(category);
CREATE INDEX IF NOT EXISTS idx_issues_severity ON issues(severity);

CREATE TABLE IF NOT EXISTS frontier (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_id TEXT NOT NULL,
    url TEXT NOT NULL,
    depth INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_frontier_audit_status ON frontier(audit_id, status);
CREATE UNIQUE INDEX IF NOT EXISTS idx_frontier_audit_url ON frontier(audit_id, url);
