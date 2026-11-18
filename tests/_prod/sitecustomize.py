# Loaded automatically (PYTHONPATH=tests/_prod) by tests/production.sh: trust the throw-away self-signed cert of the local TLS proxy.
import ssl
ssl._create_default_https_context = ssl._create_unverified_context
