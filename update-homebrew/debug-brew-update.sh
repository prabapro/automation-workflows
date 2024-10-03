#!/bin/zsh

# Define script directory dynamically
SCRIPT_DIR="${0:A:h}"
MAIN_SCRIPT="$SCRIPT_DIR/brew-update.sh"
MOCK_SCRIPT="$SCRIPT_DIR/mock_brew.sh"

# Create mock_brew script
create_mock_brew() {
    cat << 'EOF' > "$MOCK_SCRIPT"
#!/bin/zsh
mock_brew() {
    case "$1" in
        "update")
            echo "==> Updating Homebrew..."
            echo "==> Downloading https://ghcr.io/v2/homebrew/portable-ruby/portable-ruby/blobs/sha256:e7340e4a1d7cc0f113686e461b93114270848cb14676e9037a1a2ff3b1a0ff32"
            echo "################################################################################################################# 100.0%"
            echo "==> Pouring portable-ruby-3.3.5.arm64_big_sur.bottle.tar.gz"
            echo "Updated 2 taps (homebrew/core and homebrew/cask)."
            echo "==> New Formulae"
            echo "afl++               crossplane          inchi               probe-rs-tools      rsgain              sysprof"
            echo "binsider            facad               polkit              repopack            setconf"
            echo "==> New Casks"
            echo "excalidrawz                                                  keyguard"
            echo "==> Outdated Formulae"
            echo "freetds                                                      netlify-cli"
            echo "You have 2 outdated formulae installed."
            echo "You can upgrade them with brew upgrade"
            echo "or list them with brew outdated."
            ;;
        "upgrade")
            echo "==> Upgrading 2 outdated packages:"
            echo "freetds 1.4.22 -> 1.4.23"
            echo "netlify-cli 17.36.3 -> 17.36.4"
            echo "==> Downloading https://ghcr.io/v2/homebrew/core/freetds/manifests/1.4.23"
            echo "################################################################################################################# 100.0%"
            echo "==> Fetching freetds"
            echo "==> Downloading https://ghcr.io/v2/homebrew/core/freetds/blobs/sha256:038c79a890f8bfa86b4ad80b023a5d2d159e5560ee9fff8cb4"
            echo "################################################################################################################# 100.0%"
            echo "==> Downloading https://ghcr.io/v2/homebrew/core/netlify-cli/manifests/17.36.4"
            echo "################################################################################################################# 100.0%"
            echo "==> Fetching netlify-cli"
            echo "==> Downloading https://ghcr.io/v2/homebrew/core/netlify-cli/blobs/sha256:3b251f6bf7d0ccd48cdaee0f9247dd484454442646438a"
            echo "################################################################################################################# 100.0%"
            echo "==> Upgrading freetds"
            echo "  1.4.22 -> 1.4.23"
            echo "==> Pouring freetds--1.4.23.arm64_sequoia.bottle.tar.gz"
            echo "🍺  /opt/homebrew/Cellar/freetds/1.4.23: 1,424 files, 16MB"
            echo "==> Running \`brew cleanup freetds\`..."
            echo "Disable this behaviour by setting HOMEBREW_NO_INSTALL_CLEANUP."
            echo "Hide these hints with HOMEBREW_NO_ENV_HINTS (see \`man brew\`)."
            echo "Removing: /opt/homebrew/Cellar/freetds/1.4.22... (1,424 files, 16MB)"
            echo "==> Upgrading netlify-cli"
            echo "  17.36.3 -> 17.36.4"
            echo "==> Pouring netlify-cli--17.36.4.arm64_sequoia.bottle.tar.gz"
            echo "🍺  /opt/homebrew/Cellar/netlify-cli/17.36.4: 23,066 files, 203.5MB"
            echo "==> Running \`brew cleanup netlify-cli\`..."
            echo "Removing: /opt/homebrew/Cellar/netlify-cli/17.36.3... (23,057 files, 203.3MB)"
            ;;
        "cleanup")
            echo "Removing: /opt/homebrew/Library/Homebrew/vendor/portable-ruby/3.3.4_1... (1,588 files, 32MB)"
            echo "==> This operation has freed approximately 32MB of disk space."
            ;;
        "--prefix")
            echo "/opt/homebrew"
            ;;
        *)
            echo "Unknown command: $1"
            ;;
    esac
}
EOF
    chmod +x "$MOCK_SCRIPT"
}

# Run the main script with mocked Homebrew commands
run_mocked_script() {
    create_mock_brew
    BREW_PATH="$MOCK_SCRIPT" zsh "$MAIN_SCRIPT"
    rm "$MOCK_SCRIPT"
}

# Run the main script normally
run_normal_script() {
    zsh "$MAIN_SCRIPT"
}

# Parse command-line arguments
case "$1" in
    "mock")
        echo "Running with mocked Homebrew commands..."
        run_mocked_script
        ;;
    "normal")
        echo "Running normally..."
        run_normal_script
        ;;
    *)
        echo "Usage: $0 [mock|normal]"
        echo "  mock   - Run with mocked Homebrew commands"
        echo "  normal - Run normally"
        exit 1
        ;;
esac