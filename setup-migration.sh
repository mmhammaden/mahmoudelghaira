#!/usr/bin/env bash
# Ghaira Migration Setup: Google Cloud + Neon + Firebase
# This script sets up the complete migration stack

set -e

echo "========================================="
echo "Ghaira Migration Setup"
echo "Google Cloud + Neon + Firebase"
echo "========================================="
echo

# Step 0: Activate venv
echo "Step 0: Activating virtual environment..."
source /home/kali/Desktop/ghaira/.venv/bin/activate
echo "done"
echo

# Step 1: Install Python dependencies
echo "Step 1: Installing Python dependencies..."
pip install -r /home/kali/Desktop/ghaira/backend/requirements.txt
echo "done"
echo

# Step 2: Database setup - guide user
echo "Step 2: Neon Database Setup"
echo "========================="
echo "You need to create a Neon project at https://neon.com"
echo "1. Create a new project and database"
echo "2. Go to Connect → copy the DIRECT (non-pooled) URL"
echo "3. It should contain sslmode=require"
echo
read -p "Paste your Neon direct URL (or press Enter to skip): " DATABASE_URL

if [ -n "$DATABASE_URL" ]; then
    echo "export DATABASE_URL='$DATABASE_URL'" > /home/kali/Desktop/ghaira/.env.local
    echo "DATABASE_URL saved to .env.local"
    
    # Initialize database
    echo "Initializing database schema..."
    psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f /home/kali/Desktop/ghaira/backend/schema.sql
    echo "Schema created successfully"
    
    # Create admin
    echo "Creating admin user..."
    python3 /home/kali/Desktop/ghaira/backend/create_admin.py
fi
echo

# Step 3: Google Cloud setup
echo "Step 3: Google Cloud Setup"
echo "========================"
echo "Installing Google Cloud CLI..."
curl https://sdk.cloud.google.com/install | bash
export PATH="$HOME/.local/bin:$PATH"
gcloud version

echo
echo "Setting up project..."
gcloud config set project mahmoudelghaira

echo "Enabling required APIs..."
gcloud services enable run.googleapis.com cloudbuild.googleapis.com \
  artifactregistry.googleapis.com secretmanager.googleapis.com

echo "Creating service account..."
gcloud iam service-accounts create ghaira-runtime \
  --display-name='Ghaira API runtime' 2>/dev/null || echo "Service account may already exist"

echo "Adding Secret Manager access..."
for SECRET in ghaira-neon-url ghaira-session-key; do
  gcloud secrets add-iam-policy-binding "$SECRET" \
    --member="serviceAccount:ghaira-runtime@mahmoudelghaira.iam.gserviceaccount.com" \
    --role="roles/secretmanager.secretAccessor" 2>/dev/null || echo "IAM binding may already exist"
done
echo

# Step 4: Neon pooled URL for Cloud Run
echo "Step 4: Neon Pooled Connection for Cloud Run"
echo "============================================"
read -p "Paste your Neon pooled URL (or press Enter to skip): " POOLED_URL

if [ -n "$POOLED_URL" ]; then
    printf '%s' "$POOLED_URL" | gcloud secrets create ghaira-neon-url \
      --replication-policy=automatic --data-file=-
    echo "Neon URL secret created"
fi

# Step 5: Session secret
echo
echo "Step 5: Session Secret"
echo "====================="
SECRET_KEY=$(python3 -c 'import secrets; print(secrets.token_hex(32))')
printf '%s' "$SECRET_KEY" | gcloud secrets create ghaira-session-key \
  --replication-policy=automatic --data-file=-
unset SECRET_KEY
echo "Session key secret created"
echo

# Step 6: Deploy to Cloud Run
echo "Step 6: Deploy to Google Cloud Run"
echo "=================================="
echo "Deploying ghaira-api to Cloud Run..."
gcloud run deploy ghaira-api \
  --source /home/kali/Desktop/ghaira/backend \
  --region europe-west1 \
  --allow-unauthenticated \
  --service-account ghaira-runtime@mahmoudelghaira.iam.gserviceaccount.com \
  --max-instances 2 \
  --memory 512Mi \
  --set-env-vars ALLOW_REGISTRATION=0,COOKIE_SECURE=1 \
  --set-secrets DATABASE_URL=ghaira-neon-url:1,SECRET_KEY=ghaira-session-key:1

echo
echo "Step 7: Test Cloud Run health"
echo "============================="
echo "Test the health endpoint:"
echo "curl https://YOUR-SERVICE-URL.runapi.dev/api/health"
echo

# Step 8: Firebase deployment
echo "Step 8: Firebase Hosting Deployment"
echo "==================================="
echo "Make sure Firebase project is on Blaze billing plan"
echo "Verify the project ID is correct"
firebase target:apply hosting exercice7 exercice7 --project mahmoudelghaira
echo
echo "Deploying Firebase..."
firebase deploy --only hosting:exercice7 --project mahmoudelghaira

echo
echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo "1. Cloud Run URL: https://ghaira-api-YOUR-ID.run.app"
echo "2. Firebase: https://exercice7.web.app"
echo "3. API routes: https://exercice7.web.app/api/..."
echo "========================================="