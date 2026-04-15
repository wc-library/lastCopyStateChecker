package api

import (
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// HoldingsResult contains both parsed library holdings and raw API response.
type HoldingsResult struct {
	Libraries   []Library
	RawResponse interface{}
}

// Client provides methods to interact with the WorldCat API.
// Using an interface allows us to mock the API for testing.
type Client interface {
	// GetLibraryHoldings retrieves libraries that hold the item with the given OCLC number.
	// Returns slice of libraries or error if the request fails.
	GetLibraryHoldings(oclcNumber string, state string) ([]Library, error)
	// GetHoldingsWithRaw retrieves libraries and returns the raw API response as well.
	// This makes a single API call and returns both parsed and raw data.
	GetHoldingsWithRaw(oclcNumber string, state string) (*HoldingsResult, error)
}

// OCLCClient implements Client using OAuth2 Client Credentials flow.
type OCLCClient struct {
	clientID      string    // OAuth2 client ID
	clientSecret  string    // OAuth2 client secret
	institutionID string    // OCLC institution symbol
	httpClient    *http.Client
	baseURL       string
	accessToken   string    // OAuth2 access token
	tokenExpiry   time.Time // When the access token expires
}

// Library represents a single library holding from the WorldCat API.
type Library struct {
	// InstitutionName is the library's name as shown in WorldCat
	InstitutionName string `json:"institutionName,omitempty"`
	// OPACURL is the link to the item in the library's catalog
	OPACURL string `json:"opacUrl,omitempty"`
	// Country and state information for filtering
	Country string `json:"country,omitempty"`
	State   string `json:"state,omitempty"`
	// InstitutionID is the OCLC institution symbol
	InstitutionID string `json:"institutionId,omitempty"`
}

// BibRecord represents a bibliographic record from the Search API.
// Using json.RawMessage for flexible parsing of fields that may be objects or strings.
type BibRecord struct {
	// OCLCNumber is the normalized OCLC number
	OCLCNumber string `json:"oclcNumber"`
	// Holdings information (the only field we actually need)
	Holdings HoldingsInfo `json:"holdings,omitempty"`
}

// HoldingsInfo contains holding summary data from the Search API.
type HoldingsInfo struct {
	// BriefRecords contains institution holding information
	BriefRecords []HoldingRecord `json:"briefRecords,omitempty"`
	// Summary holdings data
	Summary []HoldingSummary `json:"summary,omitempty"`
}

// HoldingRecord represents a single institution holding.
// Fields use omitempty since API may not return all fields.
type HoldingRecord struct {
	InstitutionID   string `json:"institutionId,omitempty"`
	InstitutionName string `json:"institutionName,omitempty"`
	OPACURL         string `json:"opacUrl,omitempty"`
	Country         string `json:"country,omitempty"`
	State           string `json:"state,omitempty"`
}

// HoldingSummary provides aggregated holdings by region/state.
type HoldingSummary struct {
	Country  string `json:"country"`
	State    string `json:"state"`
	Count    int    `json:"count"`
}

// HoldingsResponse represents the API response structure.
type HoldingsResponse struct {
	Libraries []Library `json:"library"`
	// Diagnostics contains error information when the request fails
	Diagnostics *Diagnostics `json:"diagnostics,omitempty"`
}

// Diagnostics represents API error information.
type Diagnostics struct {
	Diagnostic struct {
		Message string `json:"message"`
	} `json:"diagnostic"`
}

// APIError represents an error returned by the WorldCat API.
type APIError struct {
	Message string
	Code    int
}

func (e *APIError) Error() string {
	return fmt.Sprintf("WorldCat API error (code %d): %s", e.Code, e.Message)
}

// NewClient creates a new OCLC API client with OAuth2 credentials.
// Uses default timeout of 30 seconds for HTTP requests.
// Required credentials:
//   - clientID: OAuth2 client ID from OCLC
//   - clientSecret: OAuth2 client secret from OCLC
//   - institutionID: OCLC institution symbol (e.g., "ILU")
func NewClient(clientID, clientSecret, institutionID string) Client {
	return &OCLCClient{
		clientID:      clientID,
		clientSecret:  clientSecret,
		institutionID: institutionID,
		httpClient: &http.Client{
			Timeout: 30 * time.Second,
		},
		// WorldCat Search API v2 base URL
		baseURL: "https://americas.discovery.api.oclc.org/worldcat/search/v2",
	}
}

// getAccessToken obtains an OAuth2 access token using client credentials.
// Tokens are cached and refreshed automatically.
func (c *OCLCClient) getAccessToken() (string, error) {
	// Return cached token if still valid (with 5 minute buffer)
	if c.accessToken != "" && time.Until(c.tokenExpiry) > 5*time.Minute {
		return c.accessToken, nil
	}

	// Request new token from OCLC OAuth2 endpoint
	authURL := "https://oauth.oclc.org/token"

	// Create Basic Auth header with base64 encoded credentials
	credentials := base64.StdEncoding.EncodeToString(
		[]byte(c.clientID + ":" + c.clientSecret),
	)

	data := url.Values{}
	data.Set("grant_type", "client_credentials")
	data.Set("scope", "wcapi")

	req, err := http.NewRequest(http.MethodPost, authURL, strings.NewReader(data.Encode()))
	if err != nil {
		return "", fmt.Errorf("failed to create auth request: %w", err)
	}

	req.Header.Set("Authorization", "Basic "+credentials)
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return "", fmt.Errorf("auth request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("auth request failed with status %d", resp.StatusCode)
	}

	var tokenResp struct {
		AccessToken string `json:"access_token"`
		ExpiresIn   int    `json:"expires_in"`
		TokenType   string `json:"token_type"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&tokenResp); err != nil {
		return "", fmt.Errorf("failed to decode token response: %w", err)
	}

	c.accessToken = tokenResp.AccessToken
	c.tokenExpiry = time.Now().Add(time.Duration(tokenResp.ExpiresIn) * time.Second)

	return c.accessToken, nil
}

// GetLibraryHoldings retrieves libraries holding the specified OCLC number.
// Delegates to GetHoldingsWithRaw to avoid duplicate API calls.
func (c *OCLCClient) GetLibraryHoldings(oclcNumber string, state string) ([]Library, error) {
	result, err := c.GetHoldingsWithRaw(oclcNumber, state)
	if err != nil {
		return nil, err
	}
	return result.Libraries, nil
}

// GetHoldingsWithRaw retrieves library holdings and returns both parsed data and raw response.
// This makes a single API call with the state filter and returns everything needed.
func (c *OCLCClient) GetHoldingsWithRaw(oclcNumber string, state string) (*HoldingsResult, error) {
	// Get OAuth2 access token
	token, err := c.getAccessToken()
	if err != nil {
		return nil, fmt.Errorf("authentication failed: %w", err)
	}

	// Build API URL for bibs-holdings endpoint with state filter
	params := url.Values{}
	params.Set("oclcNumber", oclcNumber)
	params.Set("holdingsAllEditions", "true")
	if state != "" {
		params.Set("heldInState", fmt.Sprintf("US-%s", state))
	}
	apiURL := fmt.Sprintf("%s/bibs-holdings?%s", c.baseURL, params.Encode())

	// Create HTTP GET request
	req, err := http.NewRequest(http.MethodGet, apiURL, nil)
	if err != nil {
		return nil, fmt.Errorf("failed to create request: %w", err)
	}

	// Add OAuth2 bearer token and required headers
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "lastCopyStateChecker/1.0")

	// Execute the request
	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("failed to execute request: %w", err)
	}
	defer resp.Body.Close()

	// Check HTTP status code
	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Message: fmt.Sprintf("HTTP %d (URL: %s): %s", resp.StatusCode, apiURL, string(body)),
			Code:    resp.StatusCode,
		}
	}

	// Read response body
	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("failed to read response body: %w", err)
	}

	// Parse into generic map for raw display
	var rawData map[string]interface{}
	if err := json.Unmarshal(body, &rawData); err != nil {
		return nil, fmt.Errorf("failed to parse raw response: %w", err)
	}

	// Parse the JSON response - bibs-holdings returns institutionHolding.briefHoldings
	var holdingsResp struct {
		BriefRecords []struct {
			OCLCNumber string `json:"oclcNumber"`
			InstitutionHolding struct {
				BriefHoldings []struct {
					InstitutionID   string `json:"institutionId"`
					InstitutionName string `json:"institutionName"`
					OPACURL         string `json:"opacUrl"`
					Country         string `json:"country"`
					State           string `json:"state"`
				} `json:"briefHoldings"`
				TotalHoldingCount int `json:"totalHoldingCount"`
			} `json:"institutionHolding"`
		} `json:"briefRecords"`
	}

	if err := json.Unmarshal(body, &holdingsResp); err != nil {
		return nil, fmt.Errorf("failed to decode holdings: %w", err)
	}

	// Convert holdings to our Library struct format
	libraries := make([]Library, 0)

	// Extract holdings from brief records (institutionHolding.briefHoldings)
	for _, record := range holdingsResp.BriefRecords {
		for _, holding := range record.InstitutionHolding.BriefHoldings {
			// Filter by state if provided (API may not filter correctly, so we double-check)
			if state != "" && !equalFoldState(holding.State, state) {
				continue
			}

			libraries = append(libraries, Library{
				InstitutionID:   holding.InstitutionID,
				InstitutionName: holding.InstitutionName,
				OPACURL:         holding.OPACURL,
				Country:         holding.Country,
				State:           holding.State,
			})
		}
	}

	return &HoldingsResult{
		Libraries:   libraries,
		RawResponse: rawData,
	}, nil
}

// equalFoldState is a case-insensitive state comparison that handles
// various state code formats (e.g., "IL" vs "Illinois").
func equalFoldState(apiState, configState string) bool {
	// Normalize both to uppercase for comparison
	// This handles both 2-letter codes and full state names
	return len(apiState) >= 2 && len(configState) >= 2 &&
		(apiState[0:2] == configState[0:2] ||
			equalFoldFull(apiState, configState))
}

// equalFoldFull does a full case-insensitive string comparison.
func equalFoldFull(a, b string) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if a[i]|0x20 != b[i]|0x20 {
			return false
		}
	}
	return true
}

// MockClient is a mock implementation of Client for testing.
type MockClient struct {
	Holdings   map[string][]Library      // oclcNumber -> libraries
	RawData    map[string]interface{}    // oclcNumber -> raw response
	Err        error                     // error to return (if set, overrides Holdings)
}

// GetLibraryHoldings implements the Client interface for MockClient.
func (m *MockClient) GetLibraryHoldings(oclcNumber string, state string) ([]Library, error) {
	if m.Err != nil {
		return nil, m.Err
	}
	return m.Holdings[oclcNumber], nil
}

// GetHoldingsWithRaw implements the Client interface for MockClient.
func (m *MockClient) GetHoldingsWithRaw(oclcNumber string, state string) (*HoldingsResult, error) {
	if m.Err != nil {
		return nil, m.Err
	}
	raw := m.RawData[oclcNumber]
	if raw == nil {
		raw = map[string]string{"oclcNumber": oclcNumber, "mock": "true"}
	}
	return &HoldingsResult{
		Libraries:   m.Holdings[oclcNumber],
		RawResponse: raw,
	}, nil
}

// SetMockHoldings is a helper to set up mock data for testing.
func (m *MockClient) SetMockHoldings(oclcNumber string, libraries []Library) {
	if m.Holdings == nil {
		m.Holdings = make(map[string][]Library)
	}
	m.Holdings[oclcNumber] = libraries
}
